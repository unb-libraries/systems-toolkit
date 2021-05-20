<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Helper\ProgressBar;
use UnbLibraries\SystemsToolkit\Robo\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;
use UnbLibraries\SystemsToolkit\Robo\RecursiveDirectoryTreeTrait;

/**
 * Class for Newspaper Page OCR commands.
 */
class NewspapersLibUnbCaPageVerifyCommand extends OcrCommand {

  use DrupalInstanceRestTrait;
  use RecursiveDirectoryTreeTrait;

  protected $issueConfig = NULL;
  protected $issueLocalFiles = [];
  protected $issueMetadataFile = NULL;
  protected $issueParentTitle = NULL;
  protected $issuePath = NULL;
  protected $issuePossibleEntityIds = [];
  protected $issueRemoteFiles = [];
  protected $options = [];
  protected $progressBar;
  protected $results = [];

  /**
   * Verify an issue page image file contains the same content as a local file.
   *
   * @param $issue_id
   * @param $page_no
   * @param $local_file_path
   * @param string[] $options
   *
   * @return bool
   * @throws \Exception
   */
  public static function verifyPageFromIds($issue_id, $page_no, $local_file_path, $options = ['instance-uri' => 'http://localhost:3095']) {
    $remote_file_path = $options['instance-uri'] . "/serials_pages/download/$issue_id/$page_no/download";
    if (!self::remoteHashIsSame($remote_file_path, $local_file_path)) {
      $contents = file_get_contents($remote_file_path);
      $md5_remote = md5($contents);
      $md5_local = self::md5Sum($local_file_path);
      throw new \Exception("File integrity mismatch : $local_file_path ($md5_local) | $remote_file_path ($md5_remote)");
    }
    return TRUE;
  }

  /**
   * @param $uri
   * @param $file
   *
   * @return bool
   */
  public static function remoteHashIsSame($uri, $file) {
    $contents = file_get_contents($uri);
    $md5_remote = md5($contents);
    $md5_local = self::md5Sum($file);
    if ($md5_remote == $md5_local) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Verify one or a tree of directories for import against the newspaper site.
   *
   * @param string $title_id
   *   The parent digital title ID.
   * @option issue-page-extension
   *   The file extension to match for issue pages.
   * @param string $file_path
   *   The tree file path.
   *
   * @option string $instance-uri
   *   The URI of the target instance.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:verify-issues-tree
   */
  public function verifyIssuesFromTree($title_id, $file_path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg']) {
    $this->options = $options;
    $this->drupalRestUri = $this->options['instance-uri'];
    $this->issueParentTitle = $title_id;
    $this->setIssuesQueue($file_path);
    $this->setValidateIssues();
    print_r($this->results);
  }

  /**
   * @param $file_path
   *
   * @throws \Exception
   */
  private function setIssuesQueue($file_path) {
    $regex = "/.*\/metadata.php$/i";
    $this->recursiveDirectoryTreeRoot = $file_path;
    $this->recursiveDirectoryFileRegex = $regex;
    $this->setDirsToIterate();
    $this->getConfirmDirs('Verify Issues');
  }

  /**
   * @throws \Exception
   */
  private function setValidateIssues() {
    $this->setUpProgressBar();
    foreach ($this->recursiveDirectories as $directory_to_process) {
      $result = $this->verifyIssueFromDir($directory_to_process);
      if (!empty($result) && $result['valid'] != TRUE) {
        $this->results[] = $result;
      }
      $this->progressBar->advance();
    }
    $this->progressBar->finish();
  }

  /**
   *
   */
  private function setUpProgressBar() {
    $issue_count = count($this->recursiveDirectories);
    $this->say("Verifying $issue_count issues...");
    $this->progressBar = new ProgressBar($this->output, $issue_count);
    $this->progressBar->setFormat('debug');
    $this->progressBar->start();
  }

  /**
   * @param $path
   *
   * @return array|mixed|null
   * @throws \Exception
   */
  private function verifyIssueFromDir($path) {
    $this->setIssueInit();
    $this->issuePath = $path;
    $this->issueMetadataFile = "$path/metadata.php";

    if (file_exists($this->issueMetadataFile)) {
      $this->setIssueConfig();
      $this->setPagesForImport();
      $this->setPossibleEntityIds();

      // No possible entities: Issue was likely never created.
      if (empty($this->issuePossibleEntityIds)) {
        return [
          'path' => $path,
          'eid' => NULL,
          'valid' => FALSE,
          'issue_created' => FALSE,
          'uri' => NULL,
        ];
      }

      $results = [];
      $this->setIssueLocalFiles();
      foreach ($this->issuePossibleEntityIds as $possible_issue_entity_id) {
        $rest_uri = "/rest/export/pages/$possible_issue_entity_id";
        $response = $this->getDrupalRestEntity($rest_uri, TRUE);
        if (empty($response)) {
          // No pages at all.
          $results[$possible_issue_entity_id] = [
            'path' => $path,
            'eid' => $possible_issue_entity_id,
            'valid' => TRUE,
            'issue_created' => TRUE,
            'uri' => "https://newspapers.lib.unb.ca/serials/{$this->issueParentTitle}/issues/$possible_issue_entity_id/",
            'failures' => [
              'count' => PHP_INT_MAX - 1,
            ],
          ];
        }
        else {
          $this->setIssueRemoteFiles($response);
          $failures = $this->getDiffRemoteLocal();
          $results[$possible_issue_entity_id] = [
            'path' => $path,
            'eid' => $possible_issue_entity_id,
            'valid' => $failures['count'] == 0 ? TRUE : FALSE,
            'issue_created' => TRUE,
            'uri' => "https://newspapers.lib.unb.ca/serials/{$this->issueParentTitle}/issues/$possible_issue_entity_id/",
            'failures' => $failures,
          ];
        }
      }
      $best_result = self::getBestResult($results);
      return $best_result;
    }
    else {
      $this->printMessage(LogLevel::WARNING, "The path $path does not contain a metadata.php file.");
    }
    return NULL;
  }

  /**
   *
   */
  private function setIssueInit() {
    $this->issueConfig = NULL;
    $this->issueLocalFiles = [];
    $this->issueMetadataFile = NULL;
    $this->issuePath = NULL;
    $this->issuePossibleEntityIds = [];
    $this->issueRemoteFiles = [];
  }

  private function setIssueConfig() {
    $rewrite_command = 'sudo php -f ' . $this->repoRoot . "/lib/systems-toolkit/rewriteConfigFile.php {$this->issuePath}/metadata.php";
    exec($rewrite_command);
    $this->issueConfig = json_decode(
      file_get_contents("$this->issueMetadataFile.json")
    );
  }

  /**
   * @throws \Exception
   */
  private function setPagesForImport() {
    $regex = "/^.+\.{$this->options['issue-page-extension']}$/i";
    $this->recursiveFileTreeRoot = $this->issuePath;
    $this->recursiveFileRegex = $regex;
    $this->recursiveFiles = [];
    $this->setFilesToIterate();
    $this->getConfirmFiles('Verify Issues', TRUE);
  }

  /**
   * @throws \Exception
   */
  private function setPossibleEntityIds() {
    $rest_uri = "/rest/export/issues/{$this->issueParentTitle}/{$this->issueConfig->volume}/{$this->issueConfig->issue}";
    $response = $this->getDrupalRestEntity($rest_uri, TRUE);
    if (!empty($response)) {
      $parent_issue_counter = [];
      foreach ($response as $remote_file) {
        $parent_issue_counter[] = $remote_file->parent_issue;
      }
      $this->issuePossibleEntityIds = array_keys(array_count_values($parent_issue_counter));
    }
  }

  /**
   *
   */
  private function setIssueLocalFiles() {
    $this->issueLocalFiles = [];
    foreach ($this->recursiveFiles as $local_file) {
      $this->issueLocalFiles[] = [
        'file' => $local_file,
        'page_no' => self::getPageNumberFromMikeFileName($local_file),
        'hash' => $this->getMd5Sum($local_file),
      ];
    }
  }

  /**
   * @param $filename
   *
   * @return string
   */
  private static function getPageNumberFromMikeFileName($filename) {
    $path_info = pathinfo($filename);
    $filename_components = explode('_', $path_info['filename']);
    return ltrim($filename_components[5], '0');
  }

  /**
   * @param $path
   *
   * @return string|null
   */
  private function getMd5Sum($path) {
    $this->printMessage(LogLevel::INFO, "Running MD5 Sum on $path....");
    return self::md5Sum($path);
  }

  /**
   * @param $path
   *
   * @return string|null
   */
  public static function md5Sum($path) {
    if (file_exists($path)) {
      return trim(md5_file($path));
    }
    return NULL;
  }

  /**
   * @param string $level
   * @param string $message
   * @param array $context
   */
  protected function printMessage($level, $message, $context = []) {
    $this->logger->log($level, $message, $context);
  }

  /**
   * @param $response
   */
  private function setIssueRemoteFiles($response) {
    $this->issueRemoteFiles = [];
    foreach ($response as $remote_file) {
      $remote_file_path = str_replace('/sites/default', '/mnt/newspapers.lib.unb.ca/prod', $remote_file->page_image__target_id);
      $this->issueRemoteFiles[] = [
        'file' => $remote_file->page_image__target_id,
        'page_no' => $remote_file->page_no,
        'hash' => $this->getMd5Sum($remote_file_path)
      ];
    }
  }

  /**
   * @return array
   */
  private function getDiffRemoteLocal() {
    $results = [
      'extra_on_remote' => $this->arrayKeyDiff($this->issueRemoteFiles, $this->issueLocalFiles, 'hash'),
      'extra_on_local' => $this->arrayKeyDiff($this->issueLocalFiles, $this->issueRemoteFiles, 'hash'),
      'dupe_on_remote' => $this->arrayKeyDupes($this->issueRemoteFiles, 'hash'),
      'dupe_on_local' => $this->arrayKeyDupes($this->issueLocalFiles, 'hash'),
      'remote_files' => $this->issueRemoteFiles,
      'local_files' => $this->issueLocalFiles,
    ];
    $results['count'] = count($results['extra_on_remote']) +
      count($results['extra_on_local']) +
      count($results['dupe_on_remote']) +
      count($results['dupe_on_local']);
    return $results;
  }

  /**
   * @param $arr1
   * @param $arr2
   * @param $key
   *
   * @return mixed
   */
  private static function arrayKeyDiff($arr1, $arr2, $key) {
    foreach ($arr1 as $idx1 => $val1) {
      $found = FALSE;
      foreach ($arr2 as $idx2 => $val2) {
        if ($val1[$key] == $val2[$key]) {
          $found = TRUE;
          break;
        }
      }
      if ($found) {
        unset($arr1[$idx1]);
      }
    }
    return $arr1;
  }

  /**
   * @param $arr1
   * @param $key
   *
   * @return array
   */
  private static function arrayKeyDupes($arr1, $key) {
    $dupes = [];
    $arr2 = $arr1;
    foreach ($arr1 as $idx1 => $val1) {
      foreach ($arr2 as $idx2 => $val2) {
        if ($val1[$key] == $val2[$key] && $idx1 != $idx2) {
          $dupes[] = $arr1[$idx1];
          $dupes[] = $arr2[$idx2];
          unset($arr1[$idx1]);
          unset($arr2[$idx2]);
          break;
        }
      }
    }
    return $dupes;
  }

  /**
   * @param $results
   *
   * @return mixed
   */
  private static function getBestResult($results) {
    $max_result = PHP_INT_MAX;
    $max_result_idx = 0;
    foreach ($results as $result_index => $result) {
      if ($result['failures']['count'] < $max_result) {
        $max_result = $result['failures']['count'];
        $max_result_idx = $result_index;
      }
    }
    return $results[$max_result_idx];
  }

}
