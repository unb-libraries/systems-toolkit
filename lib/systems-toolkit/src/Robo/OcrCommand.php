<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use League\Csv\Reader;
use League\Csv\Statement;
use Robo\Robo;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\Robo\DockerCommandTrait;
use UnbLibraries\SystemsToolkit\Robo\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\Robo\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for OcrCommand Robo commands.
 */
class OcrCommand extends SystemsToolkitCommand {

  use DockerCommandTrait;
  use QueuedParallelExecTrait;
  use RecursiveFileTreeTrait;

  /**
   * Tesseract metrics stored for current eval.
   *
   * @var array
   */
  private $metrics = [];

  /**
   * The docker image to use for Tesseract commands.
   *
   * @var string
   */
  private $tesseractImage = NULL;

  /**
   * Get the tesseract docker image from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setTesseractImage() {
    $this->tesseractImage = Robo::Config()->get('syskit.imaging.tesseractImage');
    if (empty($this->tesseractImage)) {
      throw new \Exception(sprintf('The tesseract docker image has not been set in the configuration file. (tesseractImage)'));
    }
  }

  /**
   * Generate metrics for OCR confidence and word count for a tree.
   *
   * @param string $root
   *   The tree root to parse.
   *
   * @option extension
   *   The extensions to match when finding files.
   * @option oem
   *   The engine to use.
   * @option lang
   *   The language to use.
   * @option threads
   *   The number of threads the OCR should use.
   * @option args
   *   Any other arguments to pass.
   *
   * @throws \Exception
   *
   * @command ocr:tesseract:tree:metrics
   */
  public function ocrTesseractMetrics($root, $options = ['extension' => 'tif', 'oem' => 1, 'lang' => 'eng', 'threads' => NULL, 'args' => NULL]) {
    $options['args'] = 'tsv';
    $this->ocrTesseractTree($root, $options);

    foreach ($this->recursiveFiles as $file) {
      $num_words = 0;
      $total_confidence=0;
      $reader = Reader::createFromPath("$file.tsv", 'r');
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
      $this->metrics[] = [
        'filename' => $file,
        'words' => $num_words,
        'total_confidence' => $total_confidence,
        'average_confidence' => $total_confidence/$num_words,
      ];
    }

    $table = new Table($this->output());
    $table->setHeaders(['File', 'Words', 'Total Confidence', 'Average Confidence'])
      ->setRows($this->metrics);
    $table->setStyle('borderless');
    $table->render();
  }

  /**
   * Generate OCR for an entire tree.
   *
   * @param string $root
   *   The tree root to parse.
   *
   * @option extension
   *   The extensions to match when finding files.
   * @option oem
   *   The engine to use.
   * @option lang
   *   The language to use.
   * @option threads
   *   The number of threads the OCR should use.
   * @option args
   *   Any other arguments to pass.
   *
   * @throws \Exception
   *
   * @command ocr:tesseract:tree
   */
  public function ocrTesseractTree($root, $options = ['extension' => 'tif', 'oem' => 1, 'lang' => 'eng', 'threads' => NULL, 'args' => NULL, 'skip-confirm' => FALSE]) {
    $regex = "/^.+\.{$options['extension']}$/i";
    $this->recursiveFileTreeRoot = $root;
    $this->recursiveFileRegex = $regex;
    $this->setFilesToIterate();
    $this->getConfirmFiles('OCR', $options['skip-confirm']);

    foreach ($this->recursiveFiles as $file_to_process) {
      $this->setAddCommandToQueue($this->getOcrFileCommand($file_to_process, $options));
    }
    if (!empty($options['threads'])) {
      $this->setThreads($options['threads']);
    }
    $this->taskDockerPull($this->tesseractImage)->run();
    $this->setRunProcessQueue('OCR');
    $this->recursiveFiles = [];
  }

  /**
   * Generate the Robo command used to generate the OCR.
   *
   * @param string $file
   *   The file to parse.
   * @option oem
   *   The engine to use.
   * @option lang
   *   The language to use.
   * @option args
   *   Any other arguments to pass.
   *
   * @return \Robo\Contract\CommandInterface
   */
  private function getOcrFileCommand($file, $options = ['oem' => 1, 'lang' => 'eng', 'args' => NULL]) {
    $ocr_file_path_info = pathinfo($file);
    return $this->taskDockerRun($this->tesseractImage)
      ->volume($ocr_file_path_info['dirname'], '/data')
      ->containerWorkdir('/data')
      ->arg('--rm')
      ->exec("--oem {$options['oem']} -l {$options['lang']} {$ocr_file_path_info['basename']} {$ocr_file_path_info['basename']} {$options['args']}");
  }

  /**
   * Generate OCR for a file.
   *
   * @param string $file
   *   The file to parse.
   * @option oem
   *   The engine to use.
   * @option lang
   *   The language to use.
   * @option args
   *   Any other arguments to pass.
   *
   * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
   *
   * @command ocr:tesseract:file
   */
  public function ocrTesseractFile($file, $options = ['oem' => 1, 'lang' => 'eng', 'args' => NULL]) {
    if (!file_exists($file)) {
      throw new FileNotFoundException("File $file not Found!");
    }
    $command = $this->getOcrFileCommand($file, $options);
    $command->run();
  }

}
