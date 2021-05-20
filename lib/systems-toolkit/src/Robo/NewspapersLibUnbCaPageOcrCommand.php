<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;
use UnbLibraries\SystemsToolkit\Robo\NewspapersLibUnbCaPageVerifyCommand;

/**
 * Class for Newspaper Page OCR commands.
 */
class NewspapersLibUnbCaPageOcrCommand extends OcrCommand {

  use DrupalInstanceRestTrait;
  use RecursiveDirectoryTreeTrait;

  const NEWSPAPERS_PAGE_REST_PATH = '/digital_serial/digital_serial_page/%s';
  const NEWSPAPERS_PAGE_CREATE_PATH = '/entity/digital_serial_page';
  const NEWSPAPERS_ISSUE_CREATE_PATH = '/entity/digital_serial_issue';

  protected $resultsLedger = [
    'success' => [],
    'fail' => [],
    'skipped' => [],
  ];
  protected $curTitleId = NULL;
  protected $curIssueId = NULL;
  protected $curIssuePath = NULL;

  /**
   * Generate and update the OCR content for a digital serial page.
   *
   * @param string $id
   *   The page entity ID.
   *
   * @option string $instance-uri
   *   The URI of the target instance.
   * @option string $output-dir
   *   The directory to store the downloaded images.
   *
   * @throws \Exception
   *
   * @usage "1"
   *
   * @command newspapers.lib.unb.ca:generate-page-ocr
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function setOcrForPage($id, $options = ['instance-uri' => 'http://localhost:3095', 'output-dir' => '/tmp']) {
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
   * Download the image of a digital serial page.
   *
   * @param string $id
   *   The page entity ID.
   *
   * @option string $instance-uri
   *   The URI of the target instance.
   * @option string $output-dir
   *   The directory to store the downloaded image.
   *
   * @throws \Exception
   *
   * @usage "1"
   *
   * @command newspapers.lib.unb.ca:get-page
   *
   * @return string|null
   *   The path to the downloaded file, NULL on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getPageImage($id, $options = ['instance-uri' => 'http://localhost:3095', 'output-dir' => '/tmp']) {
    $this->drupalRestUri = $options['instance-uri'];
    $page_details = $this->getDrupalRestEntity("/digital_serial/digital_serial_page/$id");
    return $this->downloadPageEntityImageFile($page_details, $options['output-dir']);
  }

  /**
   * Download the page image file attached to an digital page entity.
   *
   * @param object $page_details
   *   The page details JSON object from Drupal.
   * @param string $output_dir
   *   The directory to store the downloaded image.
   *
   * @return string|null
   *   The path to the downloaded file, NULL on failure.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function downloadPageEntityImageFile($page_details, $output_dir ='/tmp') {
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
    return NULL;
  }

  /**
   * Update the OCR field values for a digital serial page.
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
  private function setPageOcr($id, $field_name, $content) {
    $this->say("Updating page #$id [$field_name]");
    $patch_content = json_encode(
      [
        $field_name => [
          [
            'value' => $content
          ]
        ],
      ]
    );
    $this->patchDrupalRestEntity(sprintf(self::NEWSPAPERS_PAGE_REST_PATH, $id), $patch_content);
  }

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
   * @option bool $no-verify
   *   Do not verify if the pages were successfully uploaded.
   * @option bool $force-ocr
   *   Run OCR on files, even if already exists.
   * @option string $webtree-path
   *   The webtree file path, used to generate DZI tiles.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:create-issues-tree
   */
  public function createIssuesFromTree($title_id, $file_path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg', 'threads' => NULL, 'no-verify' => FALSE, 'force-ocr' => FALSE, 'webtree-path' => NULL]) {
    $regex = "/.*\/metadata.php$/i";
    $this->recursiveDirectoryTreeRoot = $file_path;
    $this->recursiveDirectoryFileRegex = $regex;
    $this->curTitleId = $title_id;
    $this->setDirsToIterate();
    $this->getConfirmDirs('Create Issues');

    // Then, run tesseract.
    $this->ocrTesseractTree(
      $file_path,
      [
        'extension' => $options['issue-page-extension'],
        'oem' => 1,
        'lang' => 'eng',
        'threads' => $options['threads'],
        'args' => 'hocr',
        'skip-confirm' => TRUE,
        'skip-existing' => !$options['force-ocr'],
      ]
    );

    // We have run OCR on the tree in threads, do not generate it at issue time.
    $options['generate-ocr'] = FALSE;

    foreach ($this->recursiveDirectories as $directory_to_process) {
      $this->curIssuePath = $directory_to_process;
      $processed_flag_file = "$directory_to_process/.nbnp_processed";
      try {
        if (!file_exists($processed_flag_file)) {
          $this->createIssueFromDir($title_id, $directory_to_process, $options);
          $this->resultsLedger['success'][] = [
            'title' => $this->curTitleId,
            'issue' => $this->curIssueId,
            'path' => $this->curIssuePath,
          ];

          shell_exec('sudo touch ' . escapeshellarg($processed_flag_file));
        } else {
          $this->say("Skipping already-imported issue - {$this->curIssuePath}");
          $this->resultsLedger['skipped'][] = [
            'path' => $this->curIssuePath,
          ];
        }
      }
      catch (Exception $e) {
        $this->resultsLedger['fail'][] = [
          'exception' => $e->getMessage(),
          'title' => $this->curTitleId,
          'issue' => $this->curIssueId,
          'path' => $this->curIssuePath,
        ];
      }
    }
    $this->recursiveDirectories = [];
    $this->io()->title('Operation Complete!');
    $this->writeImportLedger();
  }

  /**
   * Write out a summary of the import in a ledger file.
   */
  private function writeImportLedger() {
    $filename = 'nbnp_import_' . date('m-d-Y_hia').'.txt';
    $filepath = getcwd() . "/$filename";
    file_put_contents($filepath, print_r($this->resultsLedger, TRUE));
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
   * @option bool $no-verify
   *   Do not verify if the pages were successfully uploaded.
   * @option bool $force-ocr
   *   Run OCR on files, even if already exists.
   * @option string $webtree-path
   *   The webtree file path, used to generate DZI tiles.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:create-issue
   */
  public function createIssueFromDir($title_id, $path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg', 'threads' => NULL, 'generate-ocr' => FALSE, 'no-verify' => FALSE, 'force-ocr' => FALSE, 'webtree-path' => NULL]) {
    $this->drupalRestUri = $options['instance-uri'];

    // Create issue
    $metadata_filepath = "$path/metadata.php";
    if (file_exists($metadata_filepath)) {
      $rewrite_command = 'sudo php -f ' . $this->repoRoot . "/lib/systems-toolkit/rewriteConfigFile.php $path/metadata.php";
      exec($rewrite_command);

      $issue_config = json_decode(
        file_get_contents("$metadata_filepath.json")
      );

      // Create digital page
      $create_content = json_encode(
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
      unset($issue_config);

      $issue_object = $this->createDrupalRestEntity(self::NEWSPAPERS_ISSUE_CREATE_PATH, $create_content);
      $issue_id = $issue_object->id[0]->value;
      $this->curIssueId = $issue_id;
      $this->say("Importing pages to Issue #$issue_id");

      if ($options['generate-ocr']) {
        $this->ocrTesseractTree(
          $path,
          [
            'extension' => $options['issue-page-extension'],
            'oem' => 1,
            'lang' => 'eng',
            'threads' => $options['threads'],
            'args' => 'hocr',
            'skip-confirm' => TRUE,
            'skip-existing' => !$options['force-ocr'],
          ]
        );
      }

      // Then, create pages for the issue
      $regex = "/^.+\.{$options['issue-page-extension']}$/i";
      $this->recursiveFileTreeRoot = $path;
      $this->recursiveFileRegex = $regex;
      $this->setFilesToIterate();
      $this->getConfirmFiles('OCR', TRUE);

      foreach ($this->recursiveFiles as $page_image) {
        if (!file_exists($page_image . '.nbnp_skip')) {
          $path_info = pathinfo($page_image);
          $filename_components = explode('_', $path_info['filename']);
          $this->createSerialPageFromFile(
            $issue_id,
            (int) $filename_components[5],
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
        $this->setRunOtherCommand("newspapers.lib.unb.ca:issue:generate-dzi {$options['webtree-path']} {$this->curIssueId} --threads={$options['threads']}");
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
   * Create a digital serial page from a source file.
   *
   * @param string $issue_id
   *   The parent issue ID.
   * @param string $page_no
   *   The page number.
   * @param string $page_sort
   *   The page sort.
   * @param string $file_path
   *   The path to the file.
   *
   * @option string $instance-uri
   *   The URI of the target instance.
   * @option bool $no-verify
   *   Do not verify the page was successfully uploaded.
   *
   * @throws \Exception
   *
   * @usage "1 10 10 /home/jsanford/test.jpg"
   *
   * @command newspapers.lib.unb.ca:create-page
   */
  public function createSerialPageFromFile($issue_id, $page_no, $page_sort, $file_path, $options = ['instance-uri' => 'http://localhost:3095', 'no-verify' => FALSE]) {
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
    $file_contents = file_get_contents($file_path);
    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    $page_no_padded = str_pad($page_no,4, '0', STR_PAD_LEFT);
    $filename_to_send = "{$issue_id}-{$page_no_padded}.{$file_extension}";
    $this->say("Creating Page [$page_no_padded] From: $file_path");
    $file_entity = $this->uploadDrupalRestFileToEntityField(
      'digital_serial_page', 'digital_serial_page', 'page_image', $file_contents, $filename_to_send
    );

    // Allow the disk to write out the file.
    sleep(2);

    // Create digital page
    $create_content = json_encode(
      [
        'parent_issue' => [
          [
            'target_id' => $issue_id,
          ]
        ],
        'page_no' => [
          [
            'value' => $page_no,
          ]
        ],
        'page_sort' => [
          [
            'value' => $page_sort,
          ]
        ],
        'page_ocr' => [
          [
            'value' => $ocr_content,
          ]
        ],
        'page_hocr' => [
          [
            'value' => $hocr_content,
          ]
        ],
        'page_image' => [
          [
            'target_id' => $file_entity->fid[0]->value,
          ]
        ],
      ]
    );

    $this->createDrupalRestEntity(self::NEWSPAPERS_PAGE_CREATE_PATH, $create_content);
    if ($options['no-verify'] == FALSE) {
      NewspapersLibUnbCaPageVerifyCommand::verifyPageFromIds($issue_id, $page_no, $file_path, $options);
    }
  }

  /**
   * Upload a file for an entity field using the Drupal REST client.
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
   * @throws \Exception
   *
   * @return object
   *   The JSON object of the file returned from the server.
   */
  protected function uploadDrupalRestFileToEntityField($entity_type_id, $entity_bundle, $field_name, $file_contents, $file_name) {
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
          'X-CSRF-Token' => $this->drupalRestToken
        ],
      ]
    );
    return json_decode((string) $this->drupalRestResponse->getBody());
  }

}
