<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\Robo\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\Robo\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Base class for SystemsToolkitOcrCommand Robo commands.
 */
class SystemsToolkitOcrCommand extends SystemsToolkitCommand {

  use QueuedParallelExecTrait;
  use RecursiveFileTreeTrait;

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
  public function ocrTesseractTree($root, $options = ['extension' => 'tif', 'oem' => 1, 'lang' => 'eng', 'threads' => NULL, 'args' => NULL]) {
    $regex = "/^.+\.{$options['extension']}$/i";
    $this->treeRoot = $root;
    $this->fileRegex = $regex;
    $this->setFilesToIterate();
    $this->getConfirmFiles('OCR');

    foreach ($this->files as $file_to_process) {
      $this->addCommandToQueue($this->getOcrFileCommand($file_to_process, $options));
    }
    if (!empty($options['threads'])) {
      $this->setThreads($options['threads']);
    }
    $this->setRunProcessQueue('OCR');
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
    return $this->taskDockerRun('unblibraries/tesseract')
      ->volume($ocr_file_path_info['dirname'], '/data')
      ->containerWorkdir('/data')
      ->arg('--rm')
      ->exec("--oem {$options['oem']} -l {$options['lang']} {$ocr_file_path_info['basename']} {$ocr_file_path_info['basename']} {$options['args']}");
  }

}
