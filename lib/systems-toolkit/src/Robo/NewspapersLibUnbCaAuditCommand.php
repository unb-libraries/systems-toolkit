<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Psr\Log\LogLevel;
use Robo\Symfony\ConsoleIO;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\RecursiveDirectoryTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;

/**
 * Class for Newspaper Page OCR commands.
 */
class NewspapersLibUnbCaAuditCommand extends OcrCommand {

  use DrupalInstanceRestTrait;
  use RecursiveDirectoryTreeTrait;

  public const NULL_STRING_PLACEHOLDER = 'LULL';
  public const ZERO_LENGTH_MD5 = 'd41d8cd98f00b204e9800998ecf8427e';

  /**
   * The number of issues audited in the current session.
   *
   * @var int
   */
  protected int $auditIssueCount = 0;

  /**
   * Issues identified as duplicates.
   *
   * @var string[]
   */
  protected array $duplicateIssues = [];

  /**
   * Issues that are suspected to be empty - no remote pages attached to them.
   *
   * @var string[]
   */
  protected array $emptyRemoteIssues = [];

  /**
   * The number of issues that have validated properly.
   *
   * @var int
   */
  protected int $goodIssueCount = 0;

  /**
   * Current issue remote images that are suspected to be duplicates.
   *
   * @var string[]
   */
  protected array $imagesDuplicateOnRemote = [];

  /**
   * Current issue remote images that are suspected to be missing.
   *
   * @var string[]
   */
  protected array $imagesMissingOnRemote = [];

  /**
   * Current issue configuration as read from the metadata file.
   *
   * @var object
   */
  protected object $issueConfig;

  /**
   * Local files that are part of the current issue.
   *
   * @var string[]
   */
  protected array $issueLocalFiles = [];

  /**
   * Path to the current issue metadata file.
   *
   * @var string
   */
  protected string $issueMetadataFile;

  /**
   * The current issue parent title entity ID.
   *
   * @var string
   */
  protected string $issueParentTitle;

  /**
   * The path to the current issue.
   *
   * @var string
   */
  protected string $issuePath;

  /**
   * Remote entity IDs that are possible matches for the current issue.
   *
   * @var string[]
   */
  protected array $issuePossibleEntityIds = [];

  /**
   * Files attached to remote version of the current issue.
   *
   * @var string[]
   */
  protected array $issueRemoteFiles = [];

  /**
   * Issues that have been identified as missing remotely.
   *
   * @var string[]
   */
  protected array $missingRemoteIssues = [];

  /**
   * The current progress bar object for the CLI.
   *
   * @var \Symfony\Component\Console\Helper\ProgressBar
   */
  protected ProgressBar $progressBar;

  /**
   * The path to the remote Drupal instance filestore, typically mounted.
   *
   * @var string
   */
  protected string $webStorageBasePath;

  /**
   * Files that have been identified as zero length - both remote and local.
   *
   * @var string[]
   */
  protected array $zeroLengthFiles = [];

  /**
   * Verifies that a page image file contains the same content as a local file.
   *
   * @param int $issue_id
   *   The issue's remote entity ID.
   * @param string $page_no
   *   The issue's remote page_no.
   * @param string $local_file_path
   *   The path to the local file.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @return bool
   *   TRUE if the files contain the same content. FALSE otherwise.
   *
   * @throws \Exception
   */
  public static function verifyPageFromIds(
    int $issue_id,
    string $page_no,
    string $local_file_path,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
    ]
  ) {
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
   * Determines if a remote file is the same as a local file.
   *
   * @param string $uri
   *   The URI to the remote file.
   * @param string $file
   *   The path to the local file.
   *
   * @return bool
   *   TRUE if the files are the same. FALSE otherwise.
   */
  public static function remoteHashIsSame(string $uri, string $file) : bool {
    $contents = file_get_contents($uri);
    $md5_remote = md5($contents);
    $md5_local = self::md5Sum($file);
    if ($md5_remote == $md5_local) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determines the MD5 hash of a file.
   *
   * @param string $path
   *   The path the the file.
   *
   * @return string
   *   The MD5 hash.
   */
  public static function md5Sum(string $path) : string {
    if (file_exists($path)) {
      return trim(md5_file($path));
    }
    return '';
  }

  /**
   * Verifies one or a tree of directories for import against a remote site.
   *
   * @param string $title_id
   *   The parent digital title ID.
   * @param string $file_path
   *   The tree file path.
   * @param string $web_storage_path
   *   The web storage path.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $issue-page-extension
   *   The file extension to match for issue pages.
   * @option $instance-uri
   *   The URI of the target instance.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:audit-tree
   * @usage 1 /mnt/issues/archive
   */
  public function auditTree(
    ConsoleIO $io,
    string $title_id,
    string $file_path,
    string $web_storage_path,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
      'issue-page-extension' => 'jpg',
    ]
  ) {
    $this->setIo($io);
    $this->options = $options;
    $this->drupalRestUri = $this->options['instance-uri'];
    $this->webStorageBasePath = $web_storage_path;
    $this->issueParentTitle = $title_id;
    $this->setIssuesQueue($file_path);
    $this->setAuditIssues();
    $this->displayAuditFailures();
  }

  /**
   * Sets up the issue queue for auditing.
   *
   * @param string $file_path
   *   The local path to recursively parse for issues.
   *
   * @throws \Exception
   */
  private function setIssuesQueue(string $file_path) {
    $regex = "/.*\/metadata.php$/i";
    $this->recursiveDirectoryTreeRoot = $file_path;
    $this->recursiveDirectoryFileRegex = $regex;
    $this->setDirsToIterate();
    $this->getConfirmDirs('Verify Issues', TRUE);
  }

  /**
   * Audits the queued issues.
   *
   * @throws \Exception
   */
  private function setAuditIssues() {
    $this->setUpProgressBar();
    foreach ($this->recursiveDirectories as $directory_to_process) {
      $this->verifyIssueFromDir($directory_to_process);
      $this->auditIssueCount++;
      $this->progressBar->advance();
    }
    $this->progressBar->setMessage("Done!");
    $this->progressBar->finish();
  }

  /**
   * Sets up the progress bar that monitors the recursive audit.
   */
  private function setUpProgressBar() {
    $issue_count = count($this->recursiveDirectories);
    $this->say("Verifying $issue_count issues...");
    $this->progressBar = new ProgressBar($this->output, $issue_count);
    $this->progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s% [%message%]');
    $this->progressBar->setMessage('Starting...');
    $this->progressBar->start();
  }

  /**
   * Verifies a local issue has been properly uploaded remotely.
   *
   * @param string $path
   *   The path to the local issue.
   *
   * @throws \Exception
   */
  private function verifyIssueFromDir(string $path) {
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
        $this->progressBar->setMessage("Processing $path [$possible_issue_entity_id]...");
        $rest_uri = "/rest/export/pages/$possible_issue_entity_id";
        $response = $this->getDrupalRestEntity($rest_uri, TRUE);
        if (empty($response)) {
          // No pages at all.
          $this->emptyRemoteIssues[] = $path;
        }
        else {
          $this->setIssueRemoteFiles($response);
          $this->auditIssue($possible_issue_entity_id, $path);
        }
      }
      return;
    }
    else {
      $this->printMessage(LogLevel::WARNING, "The path $path does not contain a metadata.php file.");
    }
  }

  /**
   * Initializes the current issue metadata values.
   */
  private function setIssueInit() {
    unset($this->issueConfig);
    $this->issueLocalFiles = [];
    $this->issueMetadataFile = '';
    $this->issuePath = '';
    $this->issuePossibleEntityIds = [];
    $this->issueRemoteFiles = [];
  }

  /**
   * Sets the current issue configuration values.
   *
   * @throws \JsonException
   */
  private function setIssueConfig() {
    $rewrite_command = 'sudo php -f ' . $this->repoRoot . "/lib/systems-toolkit/rewriteConfigFile.php {$this->issuePath}/metadata.php";
    exec($rewrite_command);
    $this->issueConfig = json_decode(
      file_get_contents("$this->issueMetadataFile.json"),
      NULL,
      512,
      JSON_THROW_ON_ERROR
    );
  }

  /**
   * Queues the issues for audit.
   *
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
   * Sets the possible remote entity IDs for the current local issue.
   *
   * @throws \Exception
   */
  private function setPossibleEntityIds() {
    $this->issuePossibleEntityIds = [];
    $date = strtotime($this->issueConfig->date);
    $day_string = date('d', $date);
    $month_string = date('m', $date);
    $year_string = date('Y', $date);

    $rest_uri = sprintf(
      "%s/serials-issue-search/%s/%s/%s/%s/%s/%s",
      $this->drupalRestUri,
      $this->issueParentTitle,
      $this->getNullifiedString($year_string),
      $this->getNullifiedString($month_string),
      $this->getNullifiedString($day_string),
      $this->getNullifiedString($this->issueConfig->volume),
      $this->getNullifiedString($this->issueConfig->issue)
    );

    try {
      $ch = curl_init();
      $timeout = 5;
      curl_setopt($ch,CURLOPT_URL, $rest_uri);
      curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, $timeout);
      $data = curl_exec($ch);
      curl_close($ch);
      $raw_response = json_decode($data, NULL, 512, JSON_THROW_ON_ERROR);
      if (!empty($raw_response->data)) {
        foreach ($raw_response->data as $entity_id) {
          $this->issuePossibleEntityIds[] = $entity_id;
        }
      }
      if (count($this->issuePossibleEntityIds) > 1) {
        $this->duplicateIssues[] = [
          'local_path' => $this->issuePath,
          'remote_entities' => $this->issuePossibleEntityIds,
        ];
      }
    }
    catch (\Exception) {
      // pass.
    }
  }

  /**
   * Constructs a non-empty string, tokenizing empty strings with a standard.
   *
   * @param string $string
   *   The string to parse.
   *
   * @return string
   *   The created non-empty string.
   */
  private function getNullifiedString(string $string) : string {
    if (empty($string)) {
      return self::NULL_STRING_PLACEHOLDER;
    }
    return $string;
  }

  /**
   * Sets up the current issue's local files.
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
   * Determines the MD5 hash of a file.
   *
   * @param string $path
   *   The path to the file.
   *
   * @return string
   *   The MD5 hash.
   */
  private function getMd5Sum(string $path) : string {
    $this->printMessage(LogLevel::INFO, "Running MD5 Sum on $path....");
    return self::md5Sum($path);
  }

  /**
   * Prints a message to the console.
   *
   * @param string $level
   *   The error level of the message.
   * @param string $message
   *   The message.
   * @param array $context
   *   The context for the message.
   */
  protected function printMessage(string $level, string $message, array $context = []) {
    $this->logger->log($level, $message, $context);
  }

  /**
   * Constructs a page number based on the standardized filepath / name.
   *
   * @param string $filename
   *   The filename to use when determining the page number.
   *
   * @return string
   *   The constructed page number.
   */
  private static function getPageNumberFromMikeFileName(string $filename) : string {
    $path_info = pathinfo($filename);
    $filename_components = explode('_', $path_info['filename']);
    return ltrim($filename_components[5], '0');
  }

  /**
   * Sets up the files that are attached to the remote issue.
   *
   * @param string[] $response
   *   The REST entity object returned from the query.
   */
  private function setIssueRemoteFiles(array $response) {
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
        'hash' => $md5_sum,
      ];
    }
  }

  /**
   * Audits if the current local issue matches the remote one.
   */
  private function auditIssue($issue_id, $path) {
    $issue_fail = FALSE;

    $missing_remote_images = self::arrayKeyDiff($this->issueLocalFiles, $this->issueRemoteFiles, 'hash');
    if (!empty($missing_remote_images)) {
      $this->imagesMissingOnRemote[] = [
        'path' => $path,
        'issue_id' => $issue_id,
        'uri' => "{$this->options['instance-uri']}/serials/{$this->issueParentTitle}/issues/$issue_id/",
        'images' => $missing_remote_images,
      ];
      $issue_fail = TRUE;
    }

    $duplicate_remote_images = self::arrayKeyDupes($this->issueRemoteFiles, 'hash');

    // Zero hash isn't really a duplicate - this should be handled elsewhere.
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
        'images' => $duplicate_remote_images,
      ];
      $issue_fail = TRUE;
    }

    if (!$issue_fail) {
      $this->goodIssueCount++;
      $this->setIssueFlagFiles($path);
    }
  }

  /**
   * Diffs elements of two associative arrays, based on element key value.
   *
   * @param string[] $arr1
   *   The first associative array to compare.
   * @param string[] $arr2
   *   The second associative array to compare.
   * @param string $key
   *   The key value to use as the duplicate comparator.
   *
   * @return string[]
   *   The difference between the two values.
   */
  private static function arrayKeyDiff(array $arr1, array $arr2, string $key) : array {
    foreach ($arr1 as $idx1 => $val1) {
      $found = FALSE;
      foreach ($arr2 as $val2) {
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
   * Determines duplicate values in an associative array, based on a key value.
   *
   * @param string[] $arr1
   *   The array to query.
   * @param string $key
   *   The array key value to query.
   *
   * @return string[]
   *   The values that are duplicates in the array.
   */
  private static function arrayKeyDupes(array $arr1, string $key) : array {
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
   * Sets the processed and verified 'flag files' for the current local issue.
   *
   * @param string $path
   *   The path to the local issue.
   */
  protected function setIssueFlagFiles(string $path) {
    // Also set the 'processed' flag as well. Old imports did not set this.
    shell_exec('sudo touch ' . escapeshellarg("$path/.nbnp_processed"));
    shell_exec('sudo touch ' . escapeshellarg("$path/.nbnp_verified"));
  }

  /**
   * Displays the audit failures from the current issue.
   */
  protected function displayAuditFailures() {
    if ($this->issueIsFullyValid()) {
      $this->syskitIo->newLine();
      $this->say("{$this->auditIssueCount} issues audited and no discrepancies found!");
      return;
    }

    $this->syskitIo->newLine();
    $this->displayMissingRemoteIssues();
    $this->displayZeroLengthFiles();
    $this->displayDuplicateIssues();
    $this->displayMissingRemotePages();
    $this->displayDuplicateRemotePages();
    $this->reportIssueFailures();
  }

  /**
   * Determines if a remote issue is fully valid.
   *
   * @return bool
   *   TRUE if the issue is valid remotely, FALSE otherwise.
   */
  protected function issueIsFullyValid() : bool {
    return empty($this->missingRemoteIssues) &&
      empty($this->zeroLengthFiles) &&
      empty($this->duplicateIssues) &&
      empty($this->imagesMissingOnRemote) &&
      empty($this->imagesDuplicateOnRemote);
  }

  /**
   * Displays the issues identified as missing remotely.
   */
  protected function displayMissingRemoteIssues() {
    if (!empty($this->missingRemoteIssues)) {
      $this->syskitIo->newLine();
      $column_names = [
        'Local Path',
      ];
      $this->outputTable('Missing Remote Issues Found!', $column_names, array_values($this->missingRemoteIssues));
      $missing_issue_count = count($this->missingRemoteIssues);
      $this->say("$missing_issue_count missing issues found.");
    }
  }

  /**
   * Outputs a table to the console IO.
   *
   * @param string $title
   *   The title of the table.
   * @param string[] $column_names
   *   The column names.
   * @param array $rows
   *   The row data.
   */
  protected function outputTable(string $title, array $column_names, array $rows) {
    $this->syskitIo->title($title);
    $table = new Table($this->output());
    $table
      ->setHeaders($column_names)
      ->setRows($rows);
    $table->render();
  }

  /**
   * Displays the files identified as zero-length from the current issue.
   */
  protected function displayZeroLengthFiles() {
    if (!empty($this->zeroLengthFiles)) {
      $this->syskitIo->newLine();
      $column_names = [
        'Path',
      ];
      $this->outputTable('Zero Length Files Found!', $column_names, array_values($this->zeroLengthFiles));
      $zero_length_count = count($this->zeroLengthFiles);
      $this->say("$zero_length_count zero length files found.");
    }
  }

  /**
   * Displays the issues identified as duplicate.
   */
  protected function displayDuplicateIssues() {
    if (!empty($this->duplicateIssues)) {
      $this->syskitIo->newLine();
      $duplicate_issues = [];
      $column_names = [
        'Local Path',
        'Title',
        'Volume',
        'Issue',
        'eid',
        'URI',
      ];
      $issue_counter = 0;

      foreach ($this->duplicateIssues as $issues) {
        $first_row = TRUE;
        $remote_entities = $issues['remote_entities'];
        sort($remote_entities);
        foreach ($remote_entities as $entity) {
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
      $this->outputTable('Ingested Issues with Duplicate Metadata Found!', $column_names, $duplicate_issues);
      $this->say("$issue_counter (possible) multiply ingested issues found.");
    }
  }

  /**
   * Displays the pages identified as missing remotely.
   */
  protected function displayMissingRemotePages() {
    $missing_pages = [];
    $column_names = [
      'eid',
      'URI',
      'Page #',
      'Local Path',
    ];
    if (!empty($this->imagesMissingOnRemote)) {
      $this->syskitIo->newLine();
      foreach ($this->imagesMissingOnRemote as $issue) {
        $first_row = TRUE;
        $page_no = array_column($issue['images'], 'page_no');
        array_multisort($page_no, SORT_ASC, $issue['images']);

        foreach ($issue['images'] as $page) {
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

  /**
   * Displays the pages identified as duplicate remotely.
   */
  protected function displayDuplicateRemotePages() {
    $missing_pages = [];
    $duplicate_pages = [];
    $column_names = [
      'eid',
      'URI',
      'Page #',
      'Local Path',
    ];
    if (!empty($this->imagesDuplicateOnRemote)) {
      $this->syskitIo->newLine();
      foreach ($this->imagesDuplicateOnRemote as $issue) {
        $first_row = TRUE;
        $page_no = array_column($issue['images'], 'page_no');
        array_multisort($page_no, SORT_ASC, $issue['images']);

        foreach ($issue['images'] as $page) {
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

  /**
   * Reports the failures from the current issue.
   */
  protected function reportIssueFailures() {
    $this->syskitIo->newLine();
    $this->say(
      sprintf(
        "%s total issues audited",
        $this->auditIssueCount
      )
    );
    $this->say(
      sprintf(
        "%s passed, %s failures",
        $this->goodIssueCount,
        $this->auditIssueCount - $this->goodIssueCount
      )
    );
  }

}
