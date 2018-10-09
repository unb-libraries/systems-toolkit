<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\Robo\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\Robo\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use Robo\Robo;

/**
 * Class for DziTilerCommand Robo commands.
 */
class DziTilerCommand extends SystemsToolkitCommand {

  use QueuedParallelExecTrait;
  use RecursiveFileTreeTrait;

  /**
   * The docker image to use for Imagemagick commands.
   *
   * @var string
   */
  private $imagemagickImage = NULL;

  /**
   * Get the tesseract docker image from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setImagingImage() {
    $this->imagemagickImage = Robo::Config()->get('syskit.imaging.imagemagickImage');
    if (empty($this->imagemagickImage)) {
      throw new \Exception(sprintf('The imagemagick docker image has not been set in the configuration file. (imagemagickImage)'));
    }
  }

  /**
   * Generate DZI tiles for an entire tree.
   *
   * @param string $root
   *   The tree root to parse.
   *
   * @option extension
   *   The extensions to match when finding files.
   * @option tile-size
   *   The tile size to use.
   * @option step
   *   The zoom step to use.
   * @option threads
   *   The number of threads the process should use.
   * @option args
   *   Any other arguments to pass.
   * @option target-uid
   *   The uid to assign the target files.
   * @option target-gid
   *   The gid to assign the target files.
   *
   * @throws \Exception
   *
   * @command dzi:generate-tiles:tree
   */
  public function dziFilesTree($root, $options = ['extension' => '.tif', 'tile-size' => '256', 'step' => '200', 'skip-confirm' => FALSE, 'threads' => NULL, 'target-uid' => '100', 'target-gid' => '102', 'skip-existing' => FALSE]) {
    $regex = "/^.+\.{$options['extension']}$/i";
    $this->recursiveFileTreeRoot = $root;
    $this->recursiveFileRegex = $regex;
    $this->setFilesToIterate();
    $this->getConfirmFiles('Generate DZI files', $options['skip-confirm']);

    foreach ($this->recursiveFiles as $file_to_process) {
      $this->setAddCommandToQueue($this->getDziTileCommand($file_to_process, $options));
    }
    if (!empty($options['threads'])) {
      $this->setThreads($options['threads']);
    }
    $this->setRunProcessQueue('Generate DZI files');
  }

  /**
   * Generate DZI tiles for a file.
   *
   * @option tile-size
   *   The tile size to use.
   * @option step
   *   The zoom step to use.
   * @option target-uid
   *   The uid to assign the target files.
   * @option target-gid
   *   The gid to assign the target files.
   *
   * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
   *
   * @command dzi:generate-tiles
   */
  public function generateDziFiles($file, $options = ['tile-size' => '256', 'step' => '200', 'target-uid' => '100', 'target-gid' => '102', 'skip-existing' => FALSE]) {
    $dzi_file_path_info = pathinfo($file);
    if (!file_exists($file)) {
      throw new FileNotFoundException("File $file not Found!");
    }
    if (!$options['skip-existing'] ||
      !file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}.dzi") ||
      !file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}_files")
    ) {
      $command = $this->getDziTileCommand($file, $options);
      $command->run();
    }
  }

  /**
   * Generate the Robo command used to generate the DZI tiles.
   *
   * @param string $file
   *   The file to parse.
   *
   * @option tile-size
   *   The tile size to use.
   * @option step
   *   The zoom step to use.
   * @option target-uid
   *   The uid to assign the target files.
   * @option target-gid
   *   The gid to assign the target files.
   *
   * @return \Robo\Contract\CommandInterface
   */
  private function getDziTileCommand($file, $options = ['tile-size' => '256', 'step' => '200', 'target-uid' => '100', 'target-gid' => '102']) {
    $dzi_file_path_info = pathinfo($file);
    $tmp_dir = "/tmp/dzi/{$dzi_file_path_info['filename']}";

    return $this->taskExecStack()
      ->stopOnFail()
      ->exec("sudo rm -rf $tmp_dir")
      ->exec("mkdir -p $tmp_dir")
      ->exec("cp $file $tmp_dir")
      ->exec("docker run -v  $tmp_dir:/data --rm {$this->imagemagickImage} /app/magick-slicer.sh -- -e jpg -i /data/{$dzi_file_path_info['basename']} -o /data/{$dzi_file_path_info['filename']} --dzi -s {$options['step']} -w {$options['tile-size']} -h {$options['tile-size']}")
      ->exec("sudo cp -r $tmp_dir/{$dzi_file_path_info['filename']}_files {$dzi_file_path_info['dirname']}/")
      ->exec("sudo chown {$options['target-uid']}:{$options['target-gid']} -R {$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}_files")
      ->exec("sudo cp $tmp_dir/{$dzi_file_path_info['filename']}.dzi {$dzi_file_path_info['dirname']}/")
      ->exec("sudo chown {$options['target-uid']}:{$options['target-gid']} {$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}.dzi")
      ->exec("sudo rm -rf $tmp_dir");
  }

}
