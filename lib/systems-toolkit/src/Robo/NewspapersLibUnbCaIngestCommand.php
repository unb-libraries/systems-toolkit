<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use GuzzleHttp\Promise\Each;
use GuzzleHttp\Promise\PromiseInterface;
use Robo\Robo;
use Symfony\Component\Process\Process;
use UnbLibraries\SystemsToolkit\DockerCleanupTrait;
use UnbLibraries\SystemsToolkit\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\NbhpSnsMessageTrait;
use UnbLibraries\SystemsToolkit\RecursiveDirectoryTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\NewspapersLibUnbCaAuditCommand;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;

/**
 * Provides methods to ingest NBNP issues into newspapers.lib.unb.ca.
 */
class NewspapersLibUnbCaIngestCommand extends OcrCommand {

  use DrupalInstanceRestTrait;
  use DockerCleanupTrait;
  use NbhpSnsMessageTrait;
  use RecursiveDirectoryTreeTrait;

  public const NEWSPAPERS_ISSUE_CREATE_PATH = '/entity/digital_serial_issue';
  public const NEWSPAPERS_PAGE_CREATE_PATH = '/entity/digital_serial_page';
  public const NEWSPAPERS_PAGE_REST_PATH = '/digital_serial/digital_serial_page/%s';

  /**
   * The ID of the current issue being processed.
   *
   * @var string
   */
  protected string $curIssueId;

  /**
   * The path to the current issue being processed.
   *
   * @var string
   */
  protected string $curIssuePath;

  /**
   * The title ID of the current issue being processed.
   *
   * @var string
   */
  protected string $curTitleId;

  /**
   * The number of issues that have been processed in the current session.
   *
   * @var int
   */
  protected int $issuesProcessed = 0;

  /**
   * The results of issues that have been processed in the current session.
   *
   * @var string[]
   */
  protected array $resultsLedger = [
    'success' => [],
    'fail' => [],
    'skipped' => [],
    'dzi_fail' => [],
  ];

  /**
   * Whether DZI generation runs in the background (tree imports).
   *
   * @var bool
   */
  protected bool $backgroundDzi = FALSE;

  /**
   * The maximum number of background DZI jobs to run at once.
   *
   * @var int
   */
  protected int $dziConcurrency = 2;

  /**
   * The running background DZI jobs.
   *
   * Each entry has the 'process', 'issue', 'path' and 'log' file.
   *
   * @var array[]
   */
  protected array $dziJobs = [];

  /**
   * The number of issues that are queued in the current session.
   *
   * @var int
   */
  protected int $totalIssues = 0;

  /**
   * Cumulative wall-clock seconds spent in each import step this session.
   *
   * @var float[]
   */
  protected array $stepTimings = [];

  /**
   * Wall-clock seconds spent in each import step for the current issue.
   *
   * @var float[]
   */
  protected array $curIssueTimings = [];

  /**
   * Pages of the current issue awaiting PDF generation.
   *
   * Each entry has the local 'image' and 'hocr' paths and the target 'pdf'
   * filename.
   *
   * @var array[]
   */
  protected array $curIssuePdfJobs = [];

  /**
   * The publication years of the issues imported (or attempted) this run.
   *
   * @var string[]
   */
  protected array $importedIssueYears = [];

  /**
   * The docker image used to build PDFs from existing hOCR.
   *
   * @var string
   */
  protected string $hocrPdfImage;

  /**
   * The default docker image used to build PDFs from existing hOCR.
   */
  public const DEFAULT_HOCR_PDF_IMAGE = 'jbarlow83/ocrmypdf:v16.10.4';

  /**
   * The DPI that page hOCR is generated at (see OcrCommand).
   */
  public const HOCR_DPI = 300;

  /**
   * Default number of issues to generate DZI tiles for at once.
   *
   * Robo passes NULL for integer options that are not given on the command
   * line, so defaults are applied in code.
   */
  public const DEFAULT_DZI_CONCURRENCY = 2;

  /**
   * Default number of pages of an issue to upload and create at once.
   */
  public const DEFAULT_UPLOAD_CONCURRENCY = 4;

  /**
   * Gets the hOCR to PDF docker image from config, with a default.
   *
   * @hook init
   */
  public function setHocrPdfImage() : void {
    $this->hocrPdfImage = Robo::Config()->get('syskit.imaging.hocrPdfImage')
      ?: self::DEFAULT_HOCR_PDF_IMAGE;
  }

  /**
   * Builds searchable PDFs for the current issue's pages from existing hOCR.
   *
   * Replaces a second Tesseract pass: the page JPG is embedded unchanged and
   * an invisible text layer is rendered from the page's hOCR. All pages of
   * the issue are built in a single container.
   *
   * @param string $title_id
   *   The parent title ID.
   * @param string $issue_path
   *   The local issue directory containing the page images and hOCR.
   * @param string $pdf_root
   *   The webtree PDF root, e.g. {webtree}/files/serials/pdf.
   * @param int $workers
   *   The number of pages to build in parallel.
   *
   * @throws \Exception
   */
  protected function setIssuePdfsFromHocr(
    string $title_id,
    string $issue_path,
    string $pdf_root,
    int $workers
  ) : void {
    if (empty($this->curIssuePdfJobs)) {
      return;
    }

    $source_root = rtrim(realpath($issue_path), '/');
    $tmp_dir = "$this->tmpDir/pdf-hocr-" . substr(md5(microtime()), 0, 7);
    if (!mkdir($tmp_dir, 0777, TRUE)) {
      throw new \Exception("Unable to create $tmp_dir");
    }

    $script_args = [];
    foreach ($this->curIssuePdfJobs as $job) {
      foreach (['image', 'hocr'] as $key) {
        $real = realpath($job[$key]);
        if (!$real || !str_starts_with($real, "$source_root/")) {
          throw new \Exception("Page file {$job[$key]} is missing or outside $source_root");
        }
        $script_args[] = '/src/' . substr($real, strlen($source_root) + 1);
      }
      $script_args[] = "/out/{$job['pdf']}";
    }

    $script = $this->repoRoot . '/lib/systems-toolkit/data/scripts/hocr2pdf.py';
    $command = sprintf(
      'docker run --rm ' . self::getSyskitContainerLabelArgs() . ' -v %s:/src:ro -v %s:/out -v %s:/hocr2pdf.py:ro --entrypoint python3 %s /hocr2pdf.py --dpi %d --workers %d %s 2>&1',
      escapeshellarg($source_root),
      escapeshellarg($tmp_dir),
      escapeshellarg($script),
      escapeshellarg($this->hocrPdfImage),
      self::HOCR_DPI,
      max(1, min($workers, count($this->curIssuePdfJobs))),
      implode(' ', array_map('escapeshellarg', $script_args))
    );

    try {
      exec($command, $output, $return);
      if ($return !== 0) {
        throw new \Exception('hocr2pdf.py failed: ' . implode("\n", $output));
      }

      $target_dir = "$pdf_root/$title_id/{$this->curIssueId}";
      if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, TRUE);
      }
      foreach ($this->curIssuePdfJobs as $job) {
        $built = "$tmp_dir/{$job['pdf']}";
        if (!file_exists($built) || !filesize($built)) {
          throw new \Exception("Expected PDF $built was not created");
        }
        $target = "$target_dir/{$job['pdf']}";
        $this->taskExecStack()
          ->stopOnFail()
          ->exec('sudo mv ' . escapeshellarg($built) . ' ' . escapeshellarg($target))
          ->exec('sudo chown 100:102 ' . escapeshellarg($target))
          ->run();
      }
      $this->say(sprintf('Built %d PDFs from hOCR in %s', count($this->curIssuePdfJobs), $target_dir));
    }
    finally {
      shell_exec('sudo rm -rf ' . escapeshellarg($tmp_dir));
    }
  }

  /**
   * Starts DZI generation for an issue as a background process.
   *
   * @param string $command_string
   *   The syskit command (and arguments) to run.
   * @param string $issue_path
   *   The local issue path, for reporting.
   */
  protected function startDziJob(string $command_string, string $issue_path) : void {
    $log = "$this->tmpDir/dzi-{$this->curIssueId}-" . substr(md5(microtime()), 0, 7) . '.log';
    $bin = $_SERVER['argv'][0];
    $process = Process::fromShellCommandline(
      escapeshellarg($bin) . " --no-ansi $command_string > " . escapeshellarg($log) . ' 2>&1'
    );
    $process->setTimeout(NULL);
    $process->start();
    $this->say("Generating DZI tiles for Issue #{$this->curIssueId} in the background.");
    $this->dziJobs[] = [
      'process' => $process,
      'issue' => $this->curIssueId,
      'path' => $issue_path,
      'log' => $log,
    ];
  }

  /**
   * Waits until no more than a given number of background DZI jobs run.
   *
   * Finished jobs are reaped; failures are reported and added to the ledger
   * under 'dzi_fail' (re-run with newspapers.lib.unb.ca:generate-missing-dzi
   * or newspapers.lib.unb.ca:issue:generate-dzi).
   *
   * @param int $max_running
   *   The number of jobs that may still be running on return.
   */
  protected function waitForDziJobs(int $max_running) : void {
    while (TRUE) {
      foreach ($this->dziJobs as $idx => $job) {
        if ($job['process']->isRunning()) {
          continue;
        }
        if ($job['process']->isSuccessful()) {
          @unlink($job['log']);
        }
        else {
          $tail = implode("\n", array_slice(file($job['log'], FILE_IGNORE_NEW_LINES) ?: [], -15));
          $this->say("DZI generation FAILED for Issue #{$job['issue']} (log: {$job['log']}):\n$tail");
          $this->resultsLedger['dzi_fail'][] = [
            'issue' => $job['issue'],
            'path' => $job['path'],
            'log' => $job['log'],
          ];
        }
        unset($this->dziJobs[$idx]);
      }
      if (count($this->dziJobs) <= $max_running) {
        return;
      }
      usleep(250000);
    }
  }

  /**
   * Describes the publication years imported this run, for notifications.
   *
   * @return string
   *   e.g. '1907', '1907-1908', or 'none' if no issues were imported.
   */
  protected function getImportedYearsString() : string {
    $years = array_unique($this->importedIssueYears);
    if (empty($years)) {
      return 'none';
    }
    sort($years);
    $first = reset($years);
    $last = end($years);
    return $first === $last ? $first : "$first-$last";
  }

  /**
   * Runs a callable, recording its wall-clock duration under a step name.
   *
   * @param string $step
   *   The step name to record the duration under.
   * @param callable $callable
   *   The work to time.
   *
   * @return mixed
   *   The value returned by the callable.
   */
  protected function timeStep(string $step, callable $callable) : mixed {
    $start = microtime(TRUE);
    try {
      return $callable();
    }
    finally {
      $elapsed = microtime(TRUE) - $start;
      $this->stepTimings[$step] = ($this->stepTimings[$step] ?? 0) + $elapsed;
      $this->curIssueTimings[$step] = ($this->curIssueTimings[$step] ?? 0) + $elapsed;
    }
  }

  /**
   * Formats a set of step timings as a single human-readable line.
   *
   * @param float[] $timings
   *   Seconds keyed by step name.
   *
   * @return string
   *   The formatted timings, e.g. "pages: 41.2s, dzi: 12.0s".
   */
  protected function formatTimings(array $timings) : string {
    $parts = [];
    foreach ($timings as $step => $seconds) {
      $parts[] = sprintf('%s: %.1fs', $step, $seconds);
    }
    return implode(', ', $parts);
  }

  /**
   * Generates and updates the OCR content for a digital serial page.
   *
   * @param string $id
   *   The page entity ID.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $instance-uri
   *   The URI of the target instance.
   * @option $output-dir
   *   The directory to store the downloaded images.
   *
   * @command newspapers.lib.unb.ca:generate-page-ocr
   * @usage 1
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function setOcrForPage(
    string $id,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
      'output-dir' => '',
    ]
  ) : void {
    if (empty($options['output-dir'])) {
      $options['output-dir'] = $this->tmpDir;
    }
    $local_file = $this->getPageImage(
      $id,
      $options
    );
    $this->ocrTesseractFile(
      $local_file,
      $options = [
        'oem' => 1,
        'lang' => 'eng',
        'output_type' => 'hocr',
      ]
    );

    // Distill down HOCR.
    $hocr_content = file_get_contents($local_file . ".hocr");
    $ocr_content = strip_tags($hocr_content);

    // Set content.
    $this->setPageOcr($id, 'page_ocr', $ocr_content);
    $this->setPageOcr($id, 'page_hocr', $hocr_content);
  }

  /**
   * Downloads the image of a digital serial page.
   *
   * @param string $id
   *   The page entity ID.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $instance-uri
   *   The URI of the target instance.
   * @option $output-dir
   *   The directory to store the downloaded image.
   *
   * @command newspapers.lib.unb.ca:get-page
   * @usage 1
   *
   * @return string
   *   The path to the downloaded file, empty on failure.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPageImage(
    string $id,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
      'output-dir' => '',
    ]
  ) : string {
    if (empty($options['output-dir'])) {
      $options['output-dir'] = $this->tmpDir;
    }
    $this->drupalRestUri = $options['instance-uri'];
    $page_details = $this->getDrupalRestEntity("/digital_serial/digital_serial_page/$id");
    return $this->downloadPageEntityImageFile($page_details, $options['output-dir']);
  }

  /**
   * Downloads the page image file attached to a digital page entity.
   *
   * @param object $page_details
   *   The page details JSON object from Drupal.
   * @param string $output_dir
   *   The directory to store the downloaded image.
   *
   * @return string
   *   The path to the downloaded file, NULL on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function downloadPageEntityImageFile(object $page_details, string $output_dir = '') : string {
    if (empty($output_dir)) {
      $output_dir = $this->tmpDir;
    }
    if (!empty($page_details->page_image[0]->url)) {
      $page_uri = $page_details->page_image[0]->url;
      $path_parts = pathinfo($page_uri);
      $output_path = "$output_dir/{$path_parts['basename']}";
      $this->say("Downloading $page_uri to $output_path...");
      $this->guzzleClient->request(
        'GET',
        $page_uri,
        [
          'sink' => $output_path,
        ]
      );
      return $output_path;
    }

    $this->say("An image file was not found for the entity.");
    return '';
  }

  /**
   * Updates the OCR field values for a digital serial page.
   *
   * @param string $id
   *   The page entity ID.
   * @param string $field_name
   *   The field name to update.
   * @param string $content
   *   The content to store in the field.
   *
   * @throws \Exception
   */
  private function setPageOcr(
    string $id,
    string $field_name,
    string $content
  ) : void {
    $this->say("Updating page #$id [$field_name]");
    $patch_content = json_encode(
      [
        $field_name => [
          [
            'value' => $content,
          ],
        ],
      ], JSON_THROW_ON_ERROR
    );
    $this->patchDrupalRestEntity(sprintf(self::NEWSPAPERS_PAGE_REST_PATH, $id), $patch_content);
  }

  /**
   * Creates digital serial issues from a tree containing files.
   *
   * @param string $title_id
   *   The parent issue ID.
   * @param string $file_path
   *   The tree file path.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $force-ocr
   *   Run OCR on files, even if already exists.
   * @option $instance-uri
   *   The URI of the target instance.
   * @option $issue-page-extension
   *   The efile extension to match for issue pages.
   * @option $no-verify
   *   Do not verify if the pages were successfully uploaded.
   * @option $threads
   *   The number of threads the OCR process should use.
   * @option $dzi-threads
   *   The number of threads the OCR process should use.
   * @option $dzi-concurrency
   *   The number of issues to generate DZI tiles for at once, in the
   *   background, while later issues are uploaded.
   * @option $upload-concurrency
   *   The number of pages of an issue to upload and create at once.
   * @option $no-notify
   *   Do not send the SNS import notifications (e.g. for test runs).
   * @option $ledger-dir
   *   The directory to write the nbnp_import_*.txt ledger to. Defaults to
   *   the current directory.
   * @option $webtree-path
   *   The webtree file path, used to generate DZI tiles.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:create-issues-tree
   * @usage 1 /mnt/issues/archive
   *
   * @nbhp
   */
  public function createIssuesFromTree(
    string $title_id,
    string $file_path,
    array $options = [
      'force-ocr' => FALSE,
      'instance-uri' => 'http://localhost:3095',
      'issue-page-extension' => 'jpg',
      'no-verify' => FALSE,
      'threads' => NULL,
      'dzi-threads' => NULL,
      'dzi-concurrency' => 2,
      'upload-concurrency' => 4,
      'no-notify' => FALSE,
      'ledger-dir' => NULL,
      'webtree-path' => NULL,
      'yes' => FALSE,
    ]
  ) : void {
    $this->options = $options;
    // Tile each issue in the background while the next one uploads.
    $this->backgroundDzi = TRUE;
    $this->dziConcurrency = max(1, (int) ($options['dzi-concurrency'] ?? self::DEFAULT_DZI_CONCURRENCY));
    $regex = "/.*\/metadata.php$/i";
    $this->recursiveDirectoryTreeRoot = $file_path;
    $this->recursiveDirectoryFileRegex = $regex;
    $this->curTitleId = $title_id;
    $this->setDirsToIterate();
    $this->getConfirmDirs('Create Issues', $this->options['yes']);

    // Pull the requisite images now, and avoid further pull attempts.
    $this->setRunOtherCommand('ocr:pull-image');
    $this->setRunOtherCommand('dzi:pull-image');
    $options['no-pull'] = TRUE;
    $options['no-cleanup'] = TRUE;

    // Queue up every file in the tree and run Tesseract now.
    $this->timeStep('ocr_prepass', fn() => $this->ocrTesseractTree(
      $file_path,
      [
        'output_type' => 'hocr',
        'extension' => $options['issue-page-extension'],
        'lang' => 'eng',
        'no-pull' => $options['no-pull'],
        'no-unset-files' => FALSE,
        'oem' => 1,
        'skip-confirm' => TRUE,
        'skip-existing' => !$options['force-ocr'],
        'threads' => $options['threads'],
        'no-cleanup' => $options['no-cleanup'],
      ]
    ));

    // We have run OCR on the tree, do not generate it at issue import time.
    $options['generate-ocr'] = FALSE;

    $this->totalIssues = count($this->recursiveDirectories);
    $this->issuesProcessed = 0;

    // Main processing loop.
    foreach ($this->recursiveDirectories as $directory_to_process) {
      $this->curIssuePath = $directory_to_process;
      $processed_flag_file = "$directory_to_process/.nbnp_processed";
      $this->issuesProcessed++;

      try {
        if (!file_exists($processed_flag_file)) {
          $this->io()->newLine();
          $this->io()->title("Creating Issue {$this->issuesProcessed}/{$this->totalIssues} [$directory_to_process]");
          $this->curIssueId = 0;
          $this->curIssueTimings = [];
          $issue_start = microtime(TRUE);
          $this->createIssueFromDir(
            $title_id,
            $directory_to_process,
            $options
          );
          $this->curIssueTimings['total'] = microtime(TRUE) - $issue_start;
          $timing_string = $this->formatTimings($this->curIssueTimings);
          $this->say("Issue #{$this->curIssueId} timings - $timing_string");
          $this->resultsLedger['success'][] = [
            'title' => $this->curTitleId,
            'issue' => $this->curIssueId,
            'path' => $this->curIssuePath,
            'timings' => $timing_string,
          ];
          shell_exec('sudo touch ' . escapeshellarg($processed_flag_file));
        }
        else {
          $this->say("Skipping already-imported issue - {$this->curIssuePath}");
          $this->resultsLedger['skipped'][] = [
            'path' => $this->curIssuePath,
          ];
        }
      }
      catch (\Exception $e) {
        $this->resultsLedger['fail'][] = [
          'exception' => $e->getMessage(),
          'title' => $this->curTitleId,
          'issue' => $this->curIssueId,
          'path' => $this->curIssuePath,
        ];
      }
    }

    // Wait for any DZI tiling still running in the background.
    $this->curIssueTimings = [];
    $this->timeStep('dzi_wait', fn() => $this->waitForDziJobs(0));

    // Tidy-up.
    $this->applicationCleanup();
    $this->recursiveDirectories = [];
    $this->io()->title('Operation Complete!');
    $output_summary = $this->getNbhpNotificationString($file_path);
    $this->io()->block($output_summary);
    if (!$options['no-notify']) {
      $this->setSendSnsMessage($output_summary);
      $this->setRunOtherCommand(sprintf(
        'newspapers.lib.unb.ca:import:notify %s %s complete',
        escapeshellarg($this->curTitleId),
        escapeshellarg($this->getImportedYearsString())
      ));
    }
    $this->writeImportLedger($options['ledger-dir'] ?? NULL);
  }

  /**
   * Generates a summary string for a NBHP import.
   *
   * @param string $path
   *   The path to report as the import source.
   *
   * @return string
   *   The summary.
   */
  private function getNbhpNotificationString(string $path) : string {
    $total_seconds = (int) floor(microtime(TRUE) - $this->commandStartTime);
    $total_time_string = gmdate("H:i:s", $total_seconds);
    // Average over issues actually attempted, not those skipped as imported.
    $issues_attempted = count($this->resultsLedger['success']) + count($this->resultsLedger['fail']);
    $seconds_each = (int) floor($total_seconds / max(1, $issues_attempted));
    $each_string = gmdate("H:i:s", $seconds_each);
    $step_string = $this->formatTimings($this->stepTimings);
    return <<<EOT
[$path] Import Finished!
Run Time: $total_time_string
Issues : {$this->issuesProcessed} ($issues_attempted imported or attempted)
Average Per Imported Issue : $each_string;
Time By Step : $step_string
EOT;
  }

  /**
   * Writes out a summary of the import in a ledger file.
   */
  private function writeImportLedger(?string $ledger_dir = NULL) : void {
    $ledger_dir = rtrim($ledger_dir ?: getcwd(), '/');
    if (!is_dir($ledger_dir) && !mkdir($ledger_dir, 0775, TRUE)) {
      $this->say("Unable to create ledger directory $ledger_dir; writing the ledger to " . getcwd());
      $ledger_dir = getcwd();
    }
    $filename = 'nbnp_import_' . date('m-d-Y_hia') . '.txt';
    $filepath = "$ledger_dir/$filename";
    file_put_contents($filepath, print_r($this->resultsLedger, TRUE));
    $this->say("Import ledger written to $filepath");
  }

  /**
   * Imports a single digital serial issue from a file path.
   *
   * @param string $title_id
   *   The parent issue ID.
   * @param string $path
   *   The tree file path.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $force-ocr
   *   Run OCR on files, even if already exists.
   * @option $generate-ocr
   *   Generate OCR for files - disable if pre-generated.
   * @option $instance-uri
   *   The URI of the target instance.
   * @option $issue-page-extension
   *   The efile extension to match for issue pages.
   * @option $no-pull
   *   Do not pull docker images prior to running.
   * @option $no-verify
   *   Do not verify if the pages were successfully uploaded.
   * @option $threads
   *   The number of threads the OCR process should use.
   * @option $dzi-threads
   *   The number of threads the OCR process should use.
   * @option $webtree-path
   *   The webtree file path, used to generate DZI tiles.
   * @option $no-cleanup
   *   Do not clean up unused docker assets after running needed containers.
   * @option $upload-concurrency
   *   The number of pages to upload and create at once.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:create-issue
   */
  public function createIssueFromDir(
    string $title_id,
    string $path,
    array $options = [
      'force-ocr' => FALSE,
      'generate-ocr' => FALSE,
      'instance-uri' => 'http://localhost:3095',
      'issue-page-extension' => 'jpg',
      'no-pull' => FALSE,
      'no-verify' => FALSE,
      'threads' => NULL,
      'dzi-threads' => NULL,
      'webtree-path' => NULL,
      'no-cleanup' => FALSE,
      'upload-concurrency' => 4,
    ]
  ) : void {
    $this->drupalRestUri = $options['instance-uri'];

    // Pull upstream docker images, if permitted.
    if (!$options['no-pull']) {
      $this->setRunOtherCommand('dzi:pull-image');
      $this->setRunOtherCommand('ocr:pull-image');
    }
    $options['no-pull'] = TRUE;

    // Create the issue.
    $metadata_filepath = "$path/metadata.php";
    if (file_exists($metadata_filepath)) {
      $rewrite_command = 'sudo php -f ' . $this->repoRoot . "/lib/systems-toolkit/rewriteConfigFile.php $path/metadata.php";
      exec($rewrite_command);

      $issue_config = json_decode(
        file_get_contents("$metadata_filepath.json"),
        NULL,
        512,
        JSON_THROW_ON_ERROR
      );
      $issue_year = substr((string) $issue_config->date, 0, 4);
      if (ctype_digit($issue_year)) {
        $this->importedIssueYears[] = $issue_year;
      }

      // Create the digital page.
      $create_content = json_encode(
        [
          'parent_title' => [
            [
              'target_id' => $title_id,
            ],
          ],
          'issue_title' => [
            [
              'value' => $this->getIssueTitle($issue_config),
            ],
          ],
          'issue_vol' => [
            [
              'value' => $issue_config->volume,
            ],
          ],
          'issue_issue' => [
            [
              'value' => $issue_config->issue,
            ],
          ],
          'issue_edition' => [
            [
              'value' => $issue_config->edition,
            ],
          ],
          'issue_date' => [
            [
              'value' => $issue_config->date,
            ],
          ],
          'issue_missingp' => [
            [
              'value' => $issue_config->missing,
            ],
          ],
          'issue_errata' => [
            [
              'value' => $issue_config->errata,
            ],
          ],
          'issue_language' => [
            [
              'value' => $issue_config->language,
            ],
          ],
          'issue_media' => [
            [
              'value' => $issue_config->media,
            ],
          ],
        ]
      );
      unset($issue_config);

      $issue_object = $this->timeStep('create_issue', fn() => $this->createDrupalRestEntity(self::NEWSPAPERS_ISSUE_CREATE_PATH, $create_content));
      $issue_id = $issue_object->id[0]->value;
      $this->curIssueId = $issue_id;
      $this->say("Importing pages to Issue #$issue_id");

      if ($options['generate-ocr']) {
        $this->ocrTesseractTree(
          $path,
          [
            'output_type' => 'hocr',
            'extension' => $options['issue-page-extension'],
            'lang' => 'eng',
            'no-pull' => $options['no-pull'],
            'no-unset-files' => FALSE,
            'oem' => 1,
            'skip-confirm' => TRUE,
            'skip-existing' => !$options['force-ocr'],
            'threads' => $options['threads'],
            'no-cleanup' => $options['no-cleanup'],
          ]
        );
      }

      // Then, create pages for the issue.
      $regex = "/^.+\.{$options['issue-page-extension']}$/i";
      $this->recursiveFileTreeRoot = $path;
      $this->recursiveFileRegex = $regex;
      $this->setFilesToIterate();
      $this->getConfirmFiles('OCR', TRUE);
      $issue_ingested_pages = [];
      $this->curIssuePdfJobs = [];
      $pages_to_create = [];
      $pages_start = microtime(TRUE);

      foreach ($this->recursiveFiles as $page_image) {
        if (!file_exists($page_image . '.nbnp_skip')) {
          $path_info = pathinfo($page_image);
          $filename_components = explode('_', $path_info['filename']);

          if (empty($filename_components[5])) {
            die("ERROR! The page image filename [{$path_info['filename']}] is not formatted as expected. Sample expected format: [003_ARG_1879_01_39_03.]");
          }

          // Some supplements have a different filename structure.
          if (trim(strtolower($filename_components[5])) == 'sup' && !empty($filename_components[6])) {
            $raw_page_no = trim($filename_components[6]);
          }
          else {
            $raw_page_no = trim($filename_components[5]);
          }
          $page_no = $this->getUniqueIssuePageNo($issue_ingested_pages, $raw_page_no);

          $pages_to_create[] = [
            'issue_id' => $issue_id,
            'page_no' => $page_no,
            'page_sort' => str_pad(
              $raw_page_no,
              4,
              '0',
              STR_PAD_LEFT
            ),
            'file_path' => $page_image,
          ];
        }
      }
      $this->createSerialPagesConcurrently(
        $pages_to_create,
        $options,
        (int) ($options['upload-concurrency'] ?? self::DEFAULT_UPLOAD_CONCURRENCY)
      );
      $pages_elapsed = microtime(TRUE) - $pages_start;
      $this->stepTimings['pages'] = ($this->stepTimings['pages'] ?? 0) + $pages_elapsed;
      $this->curIssueTimings['pages'] = ($this->curIssueTimings['pages'] ?? 0) + $pages_elapsed;

      if (!empty($options['webtree-path'])) {
        if ($options['no-cleanup']) {
          $no_cleanup_string='--no-cleanup';
        }
        else {
          $no_cleanup_string = '';
        }
        $dzi_command = "newspapers.lib.unb.ca:issue:generate-dzi {$options['webtree-path']}/files/serials/pages {$options['webtree-path']}/files/serials/dzi {$title_id} {$this->curIssueId} --threads={$options['dzi-threads']} --no-pull $no_cleanup_string";
        if ($this->backgroundDzi) {
          // Keep at most dzi-concurrency tiling jobs running.
          $this->timeStep('dzi_wait', fn() => $this->waitForDziJobs($this->dziConcurrency - 1));
          $this->startDziJob($dzi_command, $path);
        }
        else {
          $this->timeStep('dzi', fn() => $this->setRunOtherCommand($dzi_command));
        }
        $this->timeStep('pdf', function () use ($title_id, $path, $options) {
          try {
            // Build the searchable PDFs from the hOCR we already have.
            $this->setIssuePdfsFromHocr(
              $title_id,
              $path,
              "{$options['webtree-path']}/files/serials/pdf",
              (int) ($options['threads'] ?: 4)
            );
          }
          catch (\Exception $e) {
            // Fall back to the original (slower) OCR-based PDF generation.
            $this->say("PDF generation from hOCR failed, falling back to OCR: {$e->getMessage()}");
            $this->setRunOtherCommand("newspapers.lib.unb.ca:issue:generate-pdf {$options['webtree-path']}/files/serials/pages {$options['webtree-path']}/files/serials/pdf {$title_id} {$this->curIssueId} --skip-confirm");
          }
        });
      }

      $this->recursiveFiles = [];
      $this->say("New issue created at:");
      $this->say($options['instance-uri'] . "/serials/$title_id/issues/$issue_id/pages");
    }
    else {
      $this->say("The path $path does not contain a metadata.php file.");
    }
  }

  /**
   * Determine the title to assign to the issue.
   *
   * @param string[] $issue_ingested_pages
   *   An array of currently ingested pages.
   *
   * @return string
   *   The issue title.
   */
  protected function getIssueTitle(
    mixed $issue_config
  ) : string {
    if (empty($issue_config->supplement_title)) {
      return $issue_config->title;
    }
    return $issue_config->supplement_title;
  }

  /**
   * Ensures each page in an issue has a unique page_no string.
   *
   * @param string[] $issue_ingested_pages
   *   An array of currently ingested pages.
   * @param string $page_no
   *   The desired page number to apply.
   *
   * @return string
   *   The unique page_no string.
   */
  protected function getUniqueIssuePageNo(
    array &$issue_ingested_pages,
    string $page_no
  ) : string {
    $counter = 0;
    $page_check = $page_no;
    while (in_array($page_check, $issue_ingested_pages)) {
      $page_check = $page_no . '_' . $counter++;
    }
    $issue_ingested_pages[] = $page_check;
    return $page_check;
  }

  /**
   * Creates a digital serial page from a source file.
   *
   * @param string $issue_id
   *   The parent issue ID.
   * @param string $page_no
   *   The page number.
   * @param string $page_sort
   *   The page sort.
   * @param string $file_path
   *   The path to the file.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $instance-uri
   *   The URI of the target instance.
   * @option $no-verify
   *   Do not verify the page was successfully uploaded.
   *
   * @command newspapers.lib.unb.ca:create-page
   * @usage "newspapers.lib.unb.ca:create-page 1 10 10 /home/jsanford/test.jpg"
   *
   * @throws \Exception
   */
  public function createSerialPageFromFile(
    string $issue_id,
    string $page_no,
    string $page_sort,
    string $file_path,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
      'no-verify' => FALSE,
    ]
  ) : void {
    $this->drupalRestUri = $options['instance-uri'];
    $this->getCreateSerialPagePromise($issue_id, $page_no, $page_sort, $file_path, $options)->wait();
  }

  /**
   * Creates several digital serial pages, uploading some at once.
   *
   * @param array[] $pages
   *   The pages, each with 'issue_id', 'page_no', 'page_sort' and 'file_path'.
   * @param string[] $options
   *   The array of available CLI options.
   * @param int $concurrency
   *   The number of pages to upload and create at once.
   *
   * @throws \Exception
   *   If any page failed. Pages already in flight are allowed to finish.
   */
  protected function createSerialPagesConcurrently(
    array $pages,
    array $options,
    int $concurrency
  ) : void {
    $this->drupalRestUri = $options['instance-uri'];
    $concurrency = max(1, $concurrency);
    $this->say(sprintf('Uploading %d pages, %d at a time.', count($pages), $concurrency));
    $errors = [];
    $promises = (function () use ($pages, $options) {
      foreach ($pages as $page) {
        yield $this->getCreateSerialPagePromise(
          $page['issue_id'],
          $page['page_no'],
          $page['page_sort'],
          $page['file_path'],
          $options
        );
      }
    })();
    Each::ofLimit(
      $promises,
      max(1, $concurrency),
      NULL,
      function ($reason) use (&$errors) {
        $errors[] = $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason;
      }
    )->wait();
    if (!empty($errors)) {
      throw new \Exception(implode("\n", $errors));
    }
  }

  /**
   * Starts uploading, creating and verifying a digital serial page.
   *
   * @param string $issue_id
   *   The parent issue ID.
   * @param string $page_no
   *   The page number.
   * @param string $page_sort
   *   The page sort.
   * @param string $file_path
   *   The path to the file.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   Resolves once the page is created and verified.
   *
   * @throws \Exception
   */
  protected function getCreateSerialPagePromise(
    string $issue_id,
    string $page_no,
    string $page_sort,
    string $file_path,
    array $options
  ) : PromiseInterface {
    // Do OCR on file.
    if (!file_exists($file_path . ".hocr")) {
      $ocr_options = [
        'oem' => 1,
        'lang' => 'eng',
        'output_type' => 'hocr',
      ];

      $this->ocrTesseractFile(
        $file_path,
        $ocr_options
      );
    }

    // Distill down HOCR.
    $hocr_content = file_get_contents($file_path . ".hocr");
    $ocr_content = strip_tags($hocr_content);

    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    $additional_pad_chars = strlen(substr(strrchr($page_no, "_"), 0));
    $page_no_padded = str_pad($page_no, 4 + $additional_pad_chars, '0', STR_PAD_LEFT);
    $filename_to_send = "{$issue_id}-{$page_no_padded}.{$file_extension}";
    $this->say("Creating Page [$page_no_padded] From: $file_path");

    // Upload file to field.
    return $this->getDrupalRestRequestPromise(
      'post',
      '/file/upload/digital_serial_page/digital_serial_page/page_image?_format=json',
      [
        'body' => file_get_contents($file_path),
        'headers' => [
          'Content-Type' => 'application/octet-stream',
          'Content-Disposition' => "file; filename=\"$filename_to_send\"",
        ],
      ]
    )->then(function ($file_entity) use ($issue_id, $page_no, $page_sort, $ocr_content, $hocr_content) {
      // Create the digital page.
      $create_content = json_encode(
        [
          'parent_issue' => [
            [
              'target_id' => $issue_id,
            ],
          ],
          'page_no' => [
            [
              'value' => $page_no,
            ],
          ],
          'page_sort' => [
            [
              'value' => $page_sort,
            ],
          ],
          'page_ocr' => [
            [
              'value' => $ocr_content,
            ],
          ],
          'page_hocr' => [
            [
              'value' => $hocr_content,
            ],
          ],
          'page_image' => [
            [
              'target_id' => $file_entity->fid[0]->value,
            ],
          ],
        ],
        JSON_THROW_ON_ERROR
      );
      return $this->getDrupalRestRequestPromise(
        'post',
        self::NEWSPAPERS_PAGE_CREATE_PATH . '?_format=json',
        [
          'body' => $create_content,
          'headers' => [
            'Content-Type' => 'application/json',
          ],
        ]
      );
    })->then(function ($page_entity) use ($issue_id, $page_no, $file_path, $filename_to_send, $options) {
      // The upload lands in a temporary location; Drupal moves the file into
      // serials/pages/{title}/{issue}/ when the page is created. Use the
      // final location from the created page.
      $stored_relative_path = $this->getStoredPageImagePath($page_entity);

      // Queue this page's PDF, named after the file Drupal actually stored.
      $stored_filename = basename($stored_relative_path ?? $filename_to_send);
      $this->curIssuePdfJobs[] = [
        'image' => $file_path,
        'hocr' => "$file_path.hocr",
        'pdf' => pathinfo($stored_filename, PATHINFO_FILENAME) . '.pdf',
      ];

      if ($options['no-verify'] == FALSE) {
        $this->verifyStoredPage($stored_relative_path, $issue_id, $page_no, $file_path, $options);
      }
      return $page_entity;
    });
  }

  /**
   * Gets a created page's image path, relative to the public files directory.
   *
   * @param object $page_entity
   *   The page entity returned by the create request.
   *
   * @return string|null
   *   The path, e.g. 'serials/pages/248/53195/53195-0009.jpg', or NULL if
   *   the response does not include a public file URL.
   */
  protected function getStoredPageImagePath(object $page_entity) : ?string {
    $url = $page_entity->page_image[0]->url ?? '';
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $marker = '/sites/default/files/';
    $pos = strpos($path, $marker);
    if ($pos === FALSE) {
      return NULL;
    }
    return rawurldecode(substr($path, $pos + strlen($marker)));
  }

  /**
   * Verifies an uploaded page image matches the local source file.
   *
   * When the webtree is mounted locally, the stored file is hashed in place
   * rather than downloaded again over HTTP. Otherwise, falls back to the
   * remote download check.
   *
   * @param string|null $stored_relative_path
   *   The stored image path relative to the public files directory, or NULL
   *   if unknown.
   * @param string $issue_id
   *   The parent issue ID.
   * @param string $page_no
   *   The page number.
   * @param string $file_path
   *   The local source file.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @throws \Exception
   */
  protected function verifyStoredPage(
    ?string $stored_relative_path,
    string $issue_id,
    string $page_no,
    string $file_path,
    array $options
  ) : void {
    if (empty($options['webtree-path']) || empty($stored_relative_path)) {
      $this->say('Stored page path unknown, verifying page over HTTP instead.');
      NewspapersLibUnbCaAuditCommand::verifyPageFromIds((int) $issue_id, $page_no, $file_path, $options);
      return;
    }

    $stored_path = $options['webtree-path'] . '/files/' . $stored_relative_path;
    $md5_local = NewspapersLibUnbCaAuditCommand::md5Sum($file_path);
    $md5_stored = '';
    // The NFS mount may briefly lag the server; recheck a few times before
    // treating a mismatch as a failure.
    for ($attempt = 0; $attempt < 5; $attempt++) {
      if ($attempt > 0) {
        clearstatcache(TRUE, $stored_path);
        sleep(1);
      }
      $md5_stored = NewspapersLibUnbCaAuditCommand::md5Sum($stored_path);
      if ($md5_stored === $md5_local) {
        return;
      }
    }
    throw new \Exception("File integrity mismatch : $file_path ($md5_local) | $stored_path ($md5_stored)");
  }

}
