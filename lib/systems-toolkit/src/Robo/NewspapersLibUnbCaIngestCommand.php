<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Symfony\ConsoleIO;
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
  ];

  /**
   * The number of issues that are queued in the current session.
   *
   * @var int
   */
  protected int $totalIssues = 0;

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
    ConsoleIO $io,
    string $id,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
      'output-dir' => '',
    ]
  ) {
    $this->setIo($io);
    if (empty($options['output-dir'])) {
      $options['output-dir'] = $this->tmpDir;
    }
    $local_file = $this->getPageImage($id, $options);
    $this->ocrTesseractFile(
      $local_file,
      $options = [
        'oem' => 1,
        'lang' => 'eng',
        'args' => 'hocr',
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
    ConsoleIO $io,
    string $id,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
      'output-dir' => '',
    ]
  ) : string {
    $this->setIo($io);
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
  private function setPageOcr(string $id, string $field_name, string $content) {
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
    ConsoleIO $io,
    string $title_id,
    string $file_path,
    array $options = [
      'force-ocr' => FALSE,
      'instance-uri' => 'http://localhost:3095',
      'issue-page-extension' => 'jpg',
      'no-verify' => FALSE,
      'threads' => NULL,
      'webtree-path' => NULL,
      'yes' => FALSE,
    ]
  ) {
    $this->setIo($io);
    $this->options = $options;
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

    // Queue up every file in the tree and run tesseract now.
    $this->ocrTesseractTree(
      $file_path,
      [
        'args' => 'hocr',
        'extension' => $options['issue-page-extension'],
        'lang' => 'eng',
        'no-pull' => $options['no-pull'],
        'no-unset-files' => FALSE,
        'oem' => 1,
        'skip-confirm' => TRUE,
        'skip-existing' => !$options['force-ocr'],
        'threads' => $options['threads'],
      ]
    );

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
          $this->syskitIo->newLine();
          $this->syskitIo->title("Creating Issue {$this->issuesProcessed}/{$this->totalIssues} [$directory_to_process]");
          $this->curIssueId = 0;
          $this->createIssueFromDir($title_id, $directory_to_process, $options);
          $this->resultsLedger['success'][] = [
            'title' => $this->curTitleId,
            'issue' => $this->curIssueId,
            'path' => $this->curIssuePath,
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

    // Tidy-up.
    $this->recursiveDirectories = [];
    $this->syskitIo->title('Operation Complete!');
    $output_summary = $this->getNbhpNotificationString($file_path);
    $this->syskitIo->block($output_summary);
    $this->setSendSnsMessage($output_summary);
    $this->setRunOtherCommand("newspapers.lib.unb.ca:import:notify $this->curTitleId $this->curIssuePath complete");
    $this->writeImportLedger();
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
    $total_seconds = microtime(TRUE) - $this->commandStartTime;
    $total_time_string = gmdate("H:i:s", $total_seconds);
    $seconds_each = $total_seconds / $this->issuesProcessed;
    $each_string = gmdate("H:i:s", $seconds_each);
    return <<<EOT
[$path] Import Finished!
Run Time: $total_time_string
Issues : {$this->issuesProcessed}
Average Per Issue : $each_string;
EOT;
  }

  /**
   * Writes out a summary of the import in a ledger file.
   */
  private function writeImportLedger() {
    $filename = 'nbnp_import_' . date('m-d-Y_hia') . '.txt';
    $filepath = getcwd() . "/$filename";
    file_put_contents($filepath, print_r($this->resultsLedger, TRUE));
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
   * @option $webtree-path
   *   The webtree file path, used to generate DZI tiles.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:create-issue
   */
  public function createIssueFromDir(
    ConsoleIO $io,
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
      'webtree-path' => NULL,
    ]
  ) {
    $this->setIo($io);
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
              'value' => $issue_config->title,
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

      $issue_object = $this->createDrupalRestEntity(self::NEWSPAPERS_ISSUE_CREATE_PATH, $create_content);
      $issue_id = $issue_object->id[0]->value;
      $this->curIssueId = $issue_id;
      $this->say("Importing pages to Issue #$issue_id");

      if ($options['generate-ocr']) {
        $this->ocrTesseractTree(
          $path,
          [
            'args' => 'hocr',
            'extension' => $options['issue-page-extension'],
            'lang' => 'eng',
            'no-pull' => $options['no-pull'],
            'no-unset-files' => FALSE,
            'oem' => 1,
            'skip-confirm' => TRUE,
            'skip-existing' => !$options['force-ocr'],
            'threads' => $options['threads'],
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

      foreach ($this->recursiveFiles as $page_image) {
        if (!file_exists($page_image . '.nbnp_skip')) {
          $path_info = pathinfo($page_image);
          $filename_components = explode('_', $path_info['filename']);
          $page_no = $this->getUniqueIssuePageNo($issue_ingested_pages, $filename_components[5]);

          $this->createSerialPageFromFile(
            $issue_id,
            $page_no,
            str_pad(
              $filename_components[5],
              4,
              '0',
              STR_PAD_LEFT
            ),
            $page_image,
            $options
          );
        }
      }

      if (!empty($options['webtree-path'])) {
        $this->setRunOtherCommand("newspapers.lib.unb.ca:issue:generate-dzi {$options['webtree-path']} {$this->curIssueId} --threads={$options['threads']} --no-pull");
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
  protected function getUniqueIssuePageNo(array &$issue_ingested_pages, string $page_no) : string {
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
    ConsoleIO $io,
    string $issue_id,
    string $page_no,
    string $page_sort,
    string $file_path,
    array $options = [
      'instance-uri' => 'http://localhost:3095',
      'no-verify' => FALSE,
    ]
  ) {
    $this->setIo($io);
    $this->drupalRestUri = $options['instance-uri'];

    // Do OCR on file.
    if (!file_exists($file_path . ".hocr")) {
      $ocr_options = [
        'oem' => 1,
        'lang' => 'eng',
        'args' => 'hocr',
      ];

      $this->ocrTesseractFile(
        $file_path,
        $ocr_options
      );
    }

    // Distill down HOCR.
    $hocr_content = file_get_contents($file_path . ".hocr");
    $ocr_content = strip_tags($hocr_content);

    // Upload file to field.
    $handle = fopen($file_path, "rb");
    $file_contents = fread($handle, filesize($file_path));
    fclose($handle);

    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    $additional_pad_chars = strlen(substr(strrchr($page_no, "_"), 0));
    $page_no_padded = str_pad($page_no, 4 + $additional_pad_chars, '0', STR_PAD_LEFT);
    $filename_to_send = "{$issue_id}-{$page_no_padded}.{$file_extension}";
    $this->say("Creating Page [$page_no_padded] From: $file_path");
    $file_entity = $this->uploadDrupalRestFileToEntityField(
      'digital_serial_page', 'digital_serial_page', 'page_image', $file_contents, $filename_to_send
    );

    // Allow the disk to write out the file.
    sleep(1);

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
      ]
    );

    $this->createDrupalRestEntity(self::NEWSPAPERS_PAGE_CREATE_PATH, $create_content);
    if ($options['no-verify'] == FALSE) {
      NewspapersLibUnbCaAuditCommand::verifyPageFromIds($issue_id, $page_no, $file_path, $options);
    }
  }

  /**
   * Uploads a file for an entity field using the Drupal REST client.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_bundle
   *   The entity bundle.
   * @param string $field_name
   *   The field name where the file will be attached.
   * @param string $file_contents
   *   The contents of the file to attach.
   * @param string $file_name
   *   The name to use for the file when attaching.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
   *
   * @return object
   *   The JSON object of the file returned from the server.
   */
  protected function uploadDrupalRestFileToEntityField(
    string $entity_type_id,
    string $entity_bundle,
    string $field_name,
    string $file_contents,
    string $file_name
  ) : object {
    $this->setUpDrupalRestClientToken();
    $post_uri = $this->drupalRestUri . "/file/upload/{$entity_type_id}/{$entity_bundle}/{$field_name}?_format=json";
    $this->say($post_uri);
    $this->drupalRestResponse = $this->guzzleClient->post(
      $post_uri,
      [
        'auth' => [$this->drupalRestUser, $this->drupalRestPassword],
        'body' => $file_contents,
        'headers' => [
          'Content-Type' => 'application/octet-stream',
          'Content-Disposition' => "file; filename=\"$file_name\"",
          'X-CSRF-Token' => $this->drupalRestToken,
        ],
      ]
    );
    return json_decode(
      (string) $this->drupalRestResponse->getBody(),
      NULL,
      512,
      JSON_THROW_ON_ERROR
    );
  }

}
