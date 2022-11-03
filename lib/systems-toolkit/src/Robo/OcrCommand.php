<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use League\Csv\Reader;
use League\Csv\Statement;
use Robo\Contract\CommandInterface;
use Robo\Robo;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\DockerCleanupTrait;
use UnbLibraries\SystemsToolkit\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for OcrCommand Robo commands.
 */
class OcrCommand extends SystemsToolkitCommand {

  use DockerCleanupTrait;
  use QueuedParallelExecTrait;
  use RecursiveFileTreeTrait;

  /**
   * Tesseract metrics stored for current eval.
   *
   * @var array
   */
  private array $metrics = [];

  /**
   * The docker image to use for Tesseract commands.
   *
   * @var string
   */
  private string $tesseractImage;

  /**
   * Gets the Tesseract docker image from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setTesseractImage() : void {
    $this->tesseractImage = Robo::Config()->get('syskit.imaging.tesseractImage');
    if (empty($this->tesseractImage)) {
      throw new \Exception(sprintf('The Tesseract docker image has not been set in the configuration file. (tesseractImage)'));
    }
  }

  /**
   * Generates metrics for OCR confidence and word count for a tree.
   *
   * @param string $root
   *   The tree root to parse.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $args
   *   Any other arguments to pass.
   * @option $extension
   *   The extensions to match when finding files.
   * @option $lang
   *   The language to use.
   * @option $oem
   *   The engine to use.
   * @option $threads
   *   The number of threads the OCR should use.
   *
   * @throws \Exception
   *
   * @command ocr:tesseract:tree:metrics
   */
  public function ocrTesseractMetrics(
    string $root,
    array $options = [
      'args' => NULL,
      'extension' => 'tif',
      'lang' => 'eng',
      'oem' => 1,
      'threads' => NULL,
    ]
  ) : void {
    $options['args'] = 'tsv';
    $options['skip-existing'] = FALSE;
    $options['skip-confirm'] = TRUE;
    $options['no-unset-files'] = TRUE;
    $options['no-pull'] = FALSE;

    $this->ocrTesseractTree(
      $root,
      $options
    );

    $running_conf_total = 0;
    $running_word_total = 0;
    $file_conf_count = 0;

    foreach ($this->recursiveFiles as $file) {
      $num_words = 0;
      $total_confidence = 0;
      $tsv_filename = "$file.tsv";
      $reader = Reader::createFromPath($tsv_filename, 'r');
      $reader->setOutputBOM(Reader::BOM_UTF8);
      $reader->setDelimiter("\t");

      $stmt = (new Statement());
      $records = $stmt->process($reader);
      foreach ($records as $record) {
        if ($record[10] != '-1') {
          $num_words++;
          $total_confidence += floatval($record[10]);
        }
      }
      $running_conf_total = $running_conf_total + ($total_confidence / $num_words);
      $running_word_total = $running_word_total + $num_words;
      $file_conf_count++;
      $this->metrics[] = [
        'filename' => $file,
        'words' => $num_words,
        'total_confidence' => $total_confidence,
        'average_confidence' => $total_confidence / $num_words,
      ];
    }

    $this->metrics[] = [
      'filename' => "mean value ($file_conf_count files)",
      'words' => $running_word_total / $file_conf_count,
      'total_confidence' => NULL,
      'average_confidence' => $running_conf_total / $file_conf_count,
    ];

    $table = new Table($this->output());
    $table->setHeaders(
      [
        'File',
        'Words',
        'Total Confidence',
        'Average Confidence',
      ]
    )->setRows($this->metrics);
    $table->setStyle('borderless');
    $table->render();
  }

  /**
   * Generates OCR for an entire tree.
   *
   * @param string $root
   *   The tree root to parse.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $args
   *   Any other arguments to pass.
   * @option $extension
   *   The extensions to match when finding files.
   * @option $lang
   *   The language to use.
   * @option $no-pull
   *   Do not pull docker images prior to running.
   * @option $no-unset-files
   *   Do not unset the recursive file stack after processing.
   * @option $oem
   *   The Tesseract engine ID to use.
   * @option $skip-confirm
   *   Skip the confirmation process, assume 'yes'.
   * @option $skip-existing
   *   If non-empty HOCR exists for the file, do not process again.
   * @option $threads
   *   The number of threads the OCR should use.
   * @option $no-cleanup
   *   Do not clean up unused docker assets after running.
   *
   * @throws \Exception
   *
   * @command ocr:tesseract:tree
   */
  public function ocrTesseractTree(
    string $root,
    array $options = [
      'args' => NULL,
      'extension' => 'tif',
      'lang' => 'eng',
      'no-pull' => FALSE,
      'no-unset-files' => FALSE,
      'oem' => 1,
      'skip-confirm' => FALSE,
      'skip-existing' => FALSE,
      'threads' => NULL,
      'no-cleanup' => FALSE,
    ]
  ) : void {
    $regex = "/^.+\.{$options['extension']}$/i";
    $this->recursiveFileTreeRoot = $root;
    $this->recursiveFileRegex = $regex;
    $this->setFilesToIterate();
    $this->getConfirmFiles('OCR', $options['skip-confirm']);

    if (!$options['no-pull']) {
      $this->setPullOcrImage();
    }
    $options['no-pull'] = TRUE;

    foreach ($this->recursiveFiles as $file_to_process) {
      if (!$this->fileHasOcrGenerated($file_to_process) || ( $this->fileHasOcrGenerated($file_to_process) && !$options['skip-existing']) ) {
        $this->setAddCommandToQueue($this->getOcrFileCommand($file_to_process, $options));
      }
    }
    if (!empty($options['threads'])) {
      $this->setThreads($options['threads']);
    }
    $this->setRunProcessQueue('OCR');
    if (!$options['no-unset-files']) {
      $this->recursiveFiles = [];
    }
    if (!isset($options['no-cleanup']) || !$options['no-cleanup']) {
      $this->applicationCleanup();
    }
  }

  /**
   * Determines if OCR has previously been generated for this file.
   *
   * @param string $filepath
   *   The file to parse.
   *
   * @return bool
   *   TRUE if OCR has been previously generated for the file.
   */
  private function fileHasOcrGenerated(string $filepath) : bool {
    $hocr_filename = "$filepath.hocr";
    if (file_exists($hocr_filename) && filesize($hocr_filename)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Generates the Robo command used to generate the OCR.
   *
   * @param string $file
   *   The file to parse.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $args
   *   Any other arguments to pass.
   * @option $lang
   *   The language to use.
   * @option $oem
   *   The Tesseract engine ID to use.
   *
   * @return \Robo\Contract\CommandInterface
   *   The command to generate OCR for the file.
   */
  private function getOcrFileCommand(
    string $file,
    array $options = [
      'args' => NULL,
      'lang' => 'eng',
      'oem' => 1,
    ]
  ) : CommandInterface {
    $ocr_file_path_info = pathinfo($file);
    return $this->taskDockerRun($this->tesseractImage)
      ->volume($ocr_file_path_info['dirname'], '/data')
      ->containerWorkdir('/data')
      ->arg('--rm')
      ->exec("--oem {$options['oem']} --dpi 300 -l {$options['lang']} {$ocr_file_path_info['basename']} {$ocr_file_path_info['basename']} {$options['args']}");
  }

  /**
   * Generates OCR for a file.
   *
   * @param string $file
   *   The file to parse.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $oem
   *   The engine to use.
   * @option $lang
   *   The language to use.
   * @option $args
   *   Any other arguments to pass.
   *
   * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
   *
   * @command ocr:tesseract:file
   */
  public function ocrTesseractFile(
    string $file,
    array $options = [
      'oem' => 1,
      'lang' => 'eng',
      'args' => NULL,
    ]
  ) : void {
    if (!file_exists($file)) {
      throw new FileNotFoundException("File $file not Found!");
    }
    $command = $this->getOcrFileCommand($file, $options);
    $command->run();
  }

  /**
   * Pulls the docker image required to generate OCR.
   *
   * @command ocr:pull-image
   */
  public function setPullOcrImage() : void {
    shell_exec("docker pull {$this->tesseractImage}");
  }

}
