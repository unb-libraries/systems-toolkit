<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\DrupalInstanceRestTrait;
use UnbLibraries\SystemsToolkit\Robo\OcrCommand;

/**
 * Provides methods to fetch and re-OCR newspapers.lib.unb.ca digital pages.
 *
 * Newspaper issue import and audit live in the nbnp-ingest tool in the
 * newspapers.lib.unb.ca repository (tools/nbnp-ingest).
 */
class NewspapersLibUnbCaIngestCommand extends OcrCommand {

  use DrupalInstanceRestTrait;

  public const NEWSPAPERS_PAGE_REST_PATH = '/digital_serial/digital_serial_page/%s';

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

}
