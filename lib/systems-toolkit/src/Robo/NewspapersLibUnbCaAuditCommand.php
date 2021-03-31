<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Psr\Log\LogLevel;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\Robo\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;
use UnbLibraries\SystemsToolkit\Robo\RecursiveDirectoryTreeTrait;

/**
 * Class for Newspaper Page OCR commands.
 */
class NewspapersLibUnbCaAuditCommand extends OcrCommand {
  use DrupalInstanceRestTrait;
  use RecursiveDirectoryTreeTrait;

  const ZERO_LENGTH_MD5 = 'd41d8cd98f00b204e9800998ecf8427e';

  protected $issueConfig = NULL;
  protected $issueLocalFiles = [];
  protected $issueMetadataFile = NULL;
  protected $issueParentTitle = NULL;
  protected $issuePath = NULL;
  protected $issuePossibleEntityIds = [];
  protected $issueRemoteFiles = [];
  protected $options = [];
  protected $progressBar;

  protected $zeroLengthFiles = [];
  protected $duplicateIssues = [];
  protected $imagesMissingOnRemote = [];
  protected $imagesDuplicateOnRemote = [];
  protected $emptyRemoteIssues = [];
  protected $missingRemoteIssues = [];
  protected $auditIssueCount = 0;
  protected $webStorageBasePath = NULL;

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
      throw new \Exception("File integrity mismatch with $local_file_path");
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
   * @param string $file_path
   *   The tree file path.
   * @param string $web_storage_path
   *   The web storage path.
   *
   * @option issue-page-extension
   *   The file extension to match for issue pages.
   * @option string $instance-uri
   *   The URI of the target instance.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:audit-tree
   */
  public function auditTree($title_id, $file_path, $web_storage_path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg']) {
    $this->options = $options;
    $this->drupalRestUri = $this->options['instance-uri'];
    $this->webStorageBasePath = $web_storage_path;
    $this->issueParentTitle = $title_id;
    $this->setIssuesQueue($file_path);
    $this->setAuditIssues();
    $this->displayAuditFailures();
  }

  protected function displayAuditFailures() {
    if (empty($this->imagesMissingOnRemote)) {
      $this->say("{$this->auditIssueCount} issues auditied and no discrepancies found!");
      return;
    }
    $this->io()->newLine(2);
    $this->displayMissingRemoteIssues();
    $this->io()->newLine();
    $this->displayZeroLengthFiles();
    $this->io()->newLine();
    $this->displayDuplicateIssues();
    $this->io()->newLine();
    $this->displayMissingRemotePages();
    $this->io()->newLine();
    $this->displayDuplicateRemotePages();
  }

  protected function displayZeroLengthFiles() {
    if (!empty($this->zeroLengthFiles)) {
      $column_names = [
        'Path'
      ];
      $this->outputTable('Zero Length Files Found!', $column_names, array_values($this->zeroLengthFiles));
      $zero_length_count = count($this->zeroLengthFiles);
      $this->say("$zero_length_count zero length files found.");
    }
  }

  protected function displayMissingRemoteIssues() {
    if (!empty($this->missingRemoteIssues)) {
      $column_names = [
        'Local Path'
      ];
      $this->outputTable('Missing Remote Issues Found!', $column_names, array_values($this->missingRemoteIssues));
      $missing_issue_count = count($this->missingRemoteIssues);
      $this->say("$missing_issue_count missing issues found.");
    }
  }

  protected function displayDuplicateIssues() {
    $duplicate_issues=[];
    $column_names = [
      'Local Path',
      'Title',
      'Volume',
      'Issue',
      'eid',
      'URI'
    ];
    $issue_counter = 0;

    foreach ($this->duplicateIssues as $issues) {
      $first_row = TRUE;
      sort($issues['remote_entities']);

      foreach($issues['remote_entities'] as $entity) {
        if ($first_row) {
          $duplicate_issues[] = [
            $issues['local_path'],
            $this->issueParentTitle,
            $this->issueConfig->volume,
            $this->issueConfig->issue,
            $entity,
            "{$this->drupalRestUri}/serials/{$this->issueParentTitle}/issues/$entity/",
          ];
          $issue_counter++;
          $first_row = FALSE;
        }
        else {
          $duplicate_issues[] = [
            NULL,
            NULL,
            NULL,
            NULL,
            $entity,
            "{$this->drupalRestUri}/serials/{$this->issueParentTitle}/issues/$entity/",
          ];
        }
      }
    }
    $this->outputTable('Multiply Ingested Remote Issues Found!', $column_names, $duplicate_issues);
    $this->say("$issue_counter multiply ingested issues found.");
  }

  protected function displayMissingRemotePages() {
    $missing_pages = [];
    $column_names = [
      'eid',
      'URI',
      'Page #',
      'Local Path'
    ];
    if (!empty($this->imagesMissingOnRemote)) {
      foreach ($this->imagesMissingOnRemote as $issue) {
        $first_row = TRUE;
        $page_no = array_column($issue['images'], 'page_no');
        array_multisort($page_no, SORT_ASC, $issue['images']);

        foreach($issue['images'] as $page) {
          if ($first_row) {
            $missing_pages[] = [
              $issue['issue_id'],
              $issue['uri'],
              $page['page_no'],
              $page['file'],
            ];
            $first_row = FALSE;
          }
          else {
            $missing_pages[] = [
              NULL,
              NULL,
              $page['page_no'],
              $page['file'],
            ];
          }
        }
      }
      $this->outputTable('Some Local Pages are Missing From Remote Issues!', $column_names, $missing_pages);
      $missing_page_count = count($missing_pages);
      $this->say("$missing_page_count missing remote pages found.");
    }
  }

  protected function displayDuplicateRemotePages() {
    $duplicate_pages = [];
    $column_names = [
      'eid',
      'URI',
      'Page #',
      'Local Path'
    ];
    if (!empty($this->imagesDuplicateOnRemote)) {
      foreach ($this->imagesDuplicateOnRemote as $issue) {
        $first_row = TRUE;
        $page_no = array_column($issue['images'], 'page_no');
        array_multisort($page_no, SORT_ASC, $issue['images']);

        foreach($issue['images'] as $page) {
          if ($first_row) {
            $duplicate_pages[] = [
              $issue['issue_id'],
              $issue['uri'],
              $page['page_no'],
              $page['file'],
            ];
            $first_row = FALSE;
          }
          else {
            $missing_pages[] = [
              NULL,
              NULL,
              $page['page_no'],
              $page['file'],
            ];
          }
        }
      }
      $this->outputTable('Remote Issues Have Duplicate Pages!', $column_names, $missing_pages);
      $duplicate_page_count = count($duplicate_pages);
      $this->say("$duplicate_page_count duplicate remote pages found.");
    }
  }

  protected function outputTable($title, $column_names, $rows) {
    $this->io()->title($title);
    $table = new Table($this->output());
    $table
      ->setHeaders($column_names)
      ->setRows($rows)
    ;
    $table->render();
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
    $this->getConfirmDirs('Verify Issues', TRUE);
  }

  /**
   * @throws \Exception
   */
  private function setAuditIssues() {
    $this->setUpProgressBar();
    foreach ($this->recursiveDirectories as $directory_to_process) {
      $this->verifyIssueFromDir($directory_to_process);
      $this->auditIssueCount++;
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
      $this->setPagesForAudit();
      $this->setPossibleEntityIds();

      // No possible entities: Issue was likely never created.
      if (empty($this->issuePossibleEntityIds)) {
        $this->missingRemoteIssues[] = [$path];
        return;
      }

      $this->setIssueLocalFiles();
      foreach ($this->issuePossibleEntityIds as $possible_issue_entity_id) {
        $rest_uri = "/rest/export/pages/$possible_issue_entity_id";
        $response = $this->getDrupalRestEntity($rest_uri, TRUE);
        if (empty($response)) {
          // No pages at all.
          $this->emptyRemoteIssues[] = $path;
        }
        else {
          $this->setIssueRemoteFiles($response);
          $this->getDiffRemoteLocal($possible_issue_entity_id, $path);
        }
      }
      return;
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
  private function setPagesForAudit() {
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
    if (count($this->issuePossibleEntityIds) > 1) {
      $this->duplicateIssues[] = [
        'local_path' => $this->issuePath,
        'remote_entities' => $this->issuePossibleEntityIds,
      ];
    }
  }

  /**
   *
   */
  private function setIssueLocalFiles() {
    $this->issueLocalFiles = [];
    foreach ($this->recursiveFiles as $local_file) {
      $md5_sum = $this->getMd5Sum($local_file);
      if ($md5_sum == self::ZERO_LENGTH_MD5) {
        $this->zeroLengthFiles[] = [$local_file];
      }
      $this->issueLocalFiles[] = [
        'file' => $local_file,
        'page_no' => self::getPageNumberFromMikeFileName($local_file),
        'hash' => $md5_sum,
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
      $remote_file_path = str_replace('/sites/default', $this->webStorageBasePath, $remote_file->page_image__target_id);
      $md5_sum = $this->getMd5Sum($remote_file_path);
      if ($md5_sum == self::ZERO_LENGTH_MD5) {
        $this->zeroLengthFiles[] = [$remote_file_path];
      }
      $this->issueRemoteFiles[] = [
        'file' => $remote_file->page_image__target_id,
        'page_no' => $remote_file->page_no,
        'hash' => $md5_sum
      ];
    }
  }

  /**
   * @return array
   */
  private function getDiffRemoteLocal($issue_id, $path) {
    $missing_remote_images = $this->arrayKeyDiff($this->issueLocalFiles, $this->issueRemoteFiles, 'hash');
    if (!empty($missing_remote_images)) {
      $this->imagesMissingOnRemote[] = [
        'path' => $path,
        'issue_id' => $issue_id,
        'uri' => "{$this->options['instance-uri']}/serials/{$this->issueParentTitle}/issues/$issue_id/",
        'images' => $missing_remote_images
      ];
    }

    $duplicate_remote_images = $this->arrayKeyDupes($this->issueRemoteFiles, 'hash');

    # Zero hash isn't really a duplicate. This should be handled elsewhere.
    foreach ($duplicate_remote_images as $duplicate_remote_image_idx => $duplicate_remote_image) {
      if (empty($duplicate_remote_image['hash'])) {
        unset($duplicate_remote_images[$duplicate_remote_image_idx]);
      }
    }

    if (!empty($duplicate_remote_images)) {
      $this->imagesDuplicateOnRemote[] = [
        'path' => $path,
        'issue_id' => $issue_id,
        'uri' => "{$this->options['instance-uri']}/serials/{$this->issueParentTitle}/issues/$issue_id/",
        'images' => $duplicate_remote_images
      ];
    }

    return;
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

}
