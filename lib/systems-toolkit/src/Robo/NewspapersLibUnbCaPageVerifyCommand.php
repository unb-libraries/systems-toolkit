<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;

/**
 * Class for Newspaper Page OCR commands.
 */
class NewspapersLibUnbCaPageVerifyCommand extends OcrCommand {

  use DrupalInstanceRestTrait;
  use RecursiveDirectoryTreeTrait;

  protected $verifications = [];

  /**
   * Create digital serial issues from a tree containing files.
   *
   * @param string $title_id
   *   The parent issue ID.
   * @option issue-page-extension
   *   The efile extension to match for issue pages.
   * @param string $file_path
   *   The tree file path.
   *
   * @option string $instance-uri
   *   The URI of the target instance.
   * @option threads
   *   The number of threads the OCR process should use.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:verify-issues-tree
   */
  public function verifyIssuesFromTree($title_id, $file_path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg', 'threads' => NULL]) {
    $regex = "/.*\/metadata.php$/i";
    $this->recursiveDirectoryTreeRoot = $file_path;
    $this->recursiveDirectoryFileRegex = $regex;
    $this->setDirsToIterate();
    $this->getConfirmDirs('Verify Issues');

    foreach ($this->recursiveDirectories as $directory_to_process) {
      $this->verifyIssueFromDir($title_id, $directory_to_process, $options);
    }

    $issue_count = count($this->verifications);
    $this->say("$issue_count Problems Found While Validating...");
    foreach ($this->verifications as $path => $verification) {
      $this->say($path);
      print_r($verification);
    }

    $this->recursiveDirectories = [];
  }

  /**
   * Import a single digital serial issue from a file path.
   *
   * @param string $title_id
   *   The parent issue ID.
   * @param string $path
   *   The tree file path.
   *
   * @option string $instance-uri
   *   The URI of the target instance.
   * @option issue-page-extension
   *   The efile extension to match for issue pages.
   * @option threads
   *   The number of threads the OCR process should use.
   * @option generate-ocr
   *   Generate OCR for files - disable if pre-generated.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:verify-issue
   */
  public function verifyIssueFromDir($title_id, $path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg', 'threads' => NULL, 'generate-ocr' => FALSE]) {
    $this->drupalRestUri = $options['instance-uri'];
    $metadata_filepath = "$path/metadata.php";

    if (file_exists($metadata_filepath)) {
      $issue_config = $issue_config = $this->getIssueConfig($path);
      $this->setPagesForImport($path, $options);
      $volume = $issue_config->volume;
      $issue = $issue_config->issue;

      // First, get all images with this metadata.
      $rest_uri = "/rest/export/issues/$title_id/$volume/$issue";
      $response = $this->getDrupalRestEntity($rest_uri);

      $issue_entity_id = NULL;
      if (!empty($response)) {
        // The REAL entity is likely the one with the most matches.
        $parent_issue_counter = [];
        foreach ($response as $remote_file) {
          $parent_issue_counter[] = $remote_file->parent_issue;
        }
        $issue_entity_id = array_keys(array_count_values($parent_issue_counter))[0];
      }

      $rest_uri = "/rest/export/pages/$issue_entity_id";
      $response = $this->getDrupalRestEntity($rest_uri);
      if (empty($response)) {
        $this->say("[ERROR] No pages found for Volume $volume Issue $issue");
        $this->verifications[$path] = [
          'valid' => 0,
          'issue_created' => 0,
          'missing_on_host' => [],
          'missing_on_local' => [],
        ];
      }
      else {
        $pages_uploaded = count($response);
        $pages_files = count($this->recursiveFiles);
        if ($pages_files == $pages_uploaded) {
          $this->say("[OK] Volume $volume Issue $issue ($pages_files pages)");
          /*
          $this->verifications[$path] = [
            'valid' => TRUE,
            'issue_created' => TRUE,
            'missing_on_host' => [],
            'missing_on_local' => [],
          ];
          */
        }
        else {
          $this->say("[ERROR] Volume $volume Issue $issue MISMATCH ($pages_files in directory, $pages_uploaded uploaded) ($path)");
          $this->verifications[$path] = [
            'valid' => 0,
            'uri' => NULL,
            'issue_created' => 1,
            'extra_on_host' => [],
            'extra_on_local' => [],
            'dupe_on_host' => [],
            'dupe_on_local' => [],
          ];

          $remote_files = [];
          $remote_files_compare = [];
          $local_files = [];

          foreach ($this->recursiveFiles as $local_file) {
            $local_files[$local_file] = $this->getMd5Sum($local_file);
          }

          foreach ($response as $remote_file) {
            $remote_file_path = str_replace('/sites/default', '/mnt/newspapers.lib.unb.ca/prod', $remote_file->page_image__target_id);
            $hash = $this->getMd5Sum($remote_file_path);
            if ($remote_file->parent_issue == $issue_entity_id) {
              $remote_files[$remote_file->page_image__target_id] = [
                'file'=> $remote_file->page_image__target_id,
                'page_no' => $remote_file->page_no,
                'hash' => $hash
              ];
              $remote_files_compare[$remote_file->page_image__target_id] = $hash;
              $parent_issue = $remote_file->parent_issue;
            }
          }

          $this->verifications[$path]['remote_files'] = $remote_files;
          $this->verifications[$path]['local_files'] = $local_files;
          $this->verifications[$path]['extra_on_host'] = array_diff($remote_files_compare, $local_files);
          $this->verifications[$path]['uri'] = "https://newspapers.lib.unb.ca/serials/$title_id/issues/$parent_issue/";
          $this->verifications[$path]['args'] = "$title_id/$volume/$issue/$issue_entity_id";


          foreach (array_diff($local_files, $remote_files_compare) as $ll_path => $hash) {
            $path_info = pathinfo($ll_path);
            $filename_components = explode('_', $path_info['filename']);
            $page_no = $filename_components[5];
            $page_sort = str_pad(
              $filename_components[5],
              4,
              '0',
              STR_PAD_LEFT
            );
            $this->verifications[$path]['extra_on_local'][] = [
              'path' => $ll_path,
              'hash' => $hash,
              'add_cmd' => "vendor/bin/syskit newspapers.lib.unb.ca:create-page $parent_issue $page_no $page_sort $ll_path --instance-uri=https://newspapers.lib.unb.ca",
            ];
          }

          foreach(array_keys($this->get_keys_for_duplicate_values($remote_files_compare)) as $remote_key)  {
            $files = [];
            foreach ($remote_files as $remote_index => $remote_iter_file) {
              if ($remote_iter_file['hash'] == $remote_key) {
                $files[] = $remote_iter_file;
              }
            }
            $this->verifications[$path]['dupe_on_host'][$remote_key] = $files;
          }

          foreach(array_keys($this->get_keys_for_duplicate_values($local_files)) as $local_key)  {
            $values = array_keys($local_files, $local_key);
            $this->verifications[$path]['dupe_on_local'][$local_key] = $values;
          }
        }

        $this->recursiveFiles=[];
      }
    }
    else {
      $this->say("The path $path does not contain a metadata.php file.");
    }
  }

  private function get_keys_for_duplicate_values($my_arr, $clean = false) {
    if ($clean) {
      return array_unique($my_arr);
    }

    $dups = $new_arr = array();
    foreach ($my_arr as $key => $val) {
      if (!isset($new_arr[$val])) {
        $new_arr[$val] = $key;
      } else {
        if (isset($dups[$val])) {
          $dups[$val][] = $key;
        } else {
          $dups[$val] = array($key);
          // Comment out the previous line, and uncomment the following line to
          // include the initial key in the dups array.
          // $dups[$val] = array($new_arr[$val], $key);
        }
      }
    }
    return $dups;
  }

  private function getMd5Sum($path) {
    if (file_exists($path)) {
      $this->say("Running MD5 Sum on $path...");
      return trim(md5_file($path));
    }
    return NULL;
  }

  private function getIssueConfig($path) {
    $rewrite_command = 'sudo php -f ' . $this->repoRoot . "/lib/systems-toolkit/rewriteConfigFile.php $path/metadata.php";
    exec($rewrite_command);
    return json_decode(
      file_get_contents("$path/metadata.php.json")
    );
  }

  private function getIssueMetaData($path, $title_id) {
    $issue_config = $this->getIssueConfig($path);

    return json_encode(
      [
        'parent_title' => [
          [
            'target_id' => $title_id,
          ]
        ],
        'issue_title' => [
          [
            'value' => $issue_config->title
          ]
        ],
        'issue_vol' => [
          [
            'value' => $issue_config->volume,
          ]
        ],
        'issue_issue' => [
          [
            'value' => $issue_config->issue,
          ]
        ],
        'issue_edition' => [
          [
            'value' => $issue_config->edition,
          ]
        ],
        'issue_date' => [
          [
            'value' => $issue_config->date,
          ]
        ],
        'issue_missingp' => [
          [
            'value' => $issue_config->missing,
          ]
        ],
        'issue_errata' => [
          [
            'value' => $issue_config->errata,
          ]
        ],
        'issue_language' => [
          [
            'value' => $issue_config->language,
          ]
        ],
        'issue_media' => [
          [
            'value' => $issue_config->media,
          ]
        ],
      ]
    );

  }

  private function setPagesForImport($path, $options) {
    $regex = "/^.+\.{$options['issue-page-extension']}$/i";
    $this->recursiveFileTreeRoot = $path;
    $this->recursiveFileRegex = $regex;
    $this->setFilesToIterate();
    $this->getConfirmFiles('OCR', TRUE);
  }

}
