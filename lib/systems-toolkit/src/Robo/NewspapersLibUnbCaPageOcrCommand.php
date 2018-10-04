<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;

/**
 * Class for Newspaper Page OCR commands.
 */
class NewspapersLibUnbCaPageOcrCommand extends OcrCommand {

  use DrupalInstanceRestTrait;
  use RecursiveDirectoryTreeTrait;

  const NEWSPAPERS_PAGE_REST_PATH = '/digital_serial/digital_serial_page/%s';
  const NEWSPAPERS_PAGE_CREATE_PATH = '/entity/digital_serial_page';
  const NEWSPAPERS_ISSUE_CREATE_PATH = '/entity/digital_serial_issue';

  /**
   * Download the page image file attached to an digital page entity.
   *
   * @param object $page_details
   *   The page details JSON object from Drupal.
   * @param string $output_dir
   *   The directory to store the downloaded image.
   *
   * @return string
   *   The path to the downloaded file, NULL on failure.
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
   * @return string
   *   The path to the downloaded file, NULL on failure.
   */
  public function getPageImage($id, $options = ['instance-uri' => 'http://localhost:3095', 'output-dir' => '/tmp']) {
    $this->drupalRestUri = $options['instance-uri'];
    $page_details = $this->getDrupalRestEntity("/digital_serial/digital_serial_page/$id");
    return $this->downloadPageEntityImageFile($page_details, $options['output-dir']);
  }

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
   *
   * @throws \Exception
   *
   * @usage "1 10 10 /home/jsanford/test.jpg"
   *
   * @command newspapers.lib.unb.ca:create-page
   */
  public function createSerialPageFromFile($issue_id, $page_no, $page_sort, $file_path, $options = ['instance-uri' => 'http://localhost:3095']) {
    $this->drupalRestUri = $options['instance-uri'];

    // Do OCR on file.
    if (!file_exists($file_path . ".hocr")) {
      $this->ocrTesseractFile(
        $file_path,
        $options = [
          'oem' => 1,
          'lang' => 'eng',
          'args' => 'hocr',
        ]
      );
    }

    // Distill down HOCR.
    $hocr_content = file_get_contents($file_path . ".hocr");
    $ocr_content = strip_tags($hocr_content);

    // Upload file to field.
    $file_contents = file_get_contents($file_path);
    $file_entity = $this->uploadDrupalRestFileToEntityField(
      'digital_serial_page', 'digital_serial_page', 'page_image', $file_contents, 'test.jpg'
    );

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

  /**
   * Create digital serial issues from a tree containing files.
   *
   * @param string $title_id
   *   The parent issue ID.
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
   * @command newspapers.lib.unb.ca:create-issues-tree
   */
  public function createIssuesFromTree($title_id, $file_path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg']) {
    $regex = "/.*\/metadata.php$/i";
    $this->recursiveDirectoryTreeRoot = $file_path;
    $this->recursiveDirectoryFileRegex = $regex;
    $this->setDirsToIterate();
    $this->getConfirmDirs('Create Issues');

    foreach ($this->recursiveDirectories as $directory_to_process) {
      createIssueFromDir($title_id, $directory_to_process, $options);
    }
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
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:create-issue
   */
  public function createIssueFromDir($title_id, $path, $options = ['instance-uri' => 'http://localhost:3095', 'issue-page-extension' => 'jpg']) {
    $this->drupalRestUri = $options['instance-uri'];

    // Create issue
    $metadata_filepath = "$path/metadata.php";
    if (file_exists($metadata_filepath)) {
      require $metadata_filepath;

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
              'value' => ISSUE_TITLE
            ]
          ],
          'issue_vol' => [
            [
              'value' => ISSUE_VOLUME,
            ]
          ],
          'issue_issue' => [
            [
              'value' => ISSUE_ISSUE,
            ]
          ],
          'issue_edition' => [
            [
              'value' => ISSUE_EDITION,
            ]
          ],
          'issue_date' => [
            [
              'value' => date( "Y-m-d", ISSUE_DATE),
            ]
          ],
          'issue_missingp' => [
            [
              'value' => MISSING_PAGES,
            ]
          ],
          'issue_errata' => [
            [
              'value' => ISSUE_ERRATA,
            ]
          ],
          'issue_language' => [
            [
              'value' => ISSUE_LANGUAGE,
            ]
          ],
          'issue_media' => [
            [
              'value' => SOURCE_MEDIA,
            ]
          ],
        ]
      );

      $issue_object = $this->createDrupalRestEntity(self::NEWSPAPERS_ISSUE_CREATE_PATH, $create_content);
      $issue_id = $issue_object->id[0]->value;
      $this->say("Importing pages to Issue #$issue_id");

      // Then, run tesseract.
      $this->ocrTesseractTree(
        $path,
        [
          'extension' => $options['issue-page-extension'],
          'oem' => 1,
          'lang' => 'eng',
          'threads' => NULL,
          'args' => 'hocr',
          'skip-confirm' => TRUE,
        ]
      );

      // Then, create pages for the issue
      foreach ($this->recursiveFiles as $page_image) {
        $path_info = pathinfo($page_image);
        $filename_components = explode('_', $path_info['filename']);
        $this->createSerialPageFromFile($issue_id, (int) $filename_components[0], $filename_components[0], $page_image);
      }

    }
    else {
      $this->say("The path $path does not contain a metadata.php file.");
    }

  }

}
