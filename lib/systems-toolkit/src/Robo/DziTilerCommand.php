<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\DockerCommandTrait;
use UnbLibraries\SystemsToolkit\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for DziTilerCommand Robo commands.
 */
class DziTilerCommand extends SystemsToolkitCommand {

  use DockerCommandTrait;
  use QueuedParallelExecTrait;
  use RecursiveFileTreeTrait;

  /**
   * The docker image to use for Imagemagick commands.
   *
   * @var string
   */
  private $imagemagickImage;

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
   * @option no-pull
   *   Do not pull docker images prior to running.
   *
   * @throws \Exception
   *
   * @command dzi:generate-tiles:tree
   */
  public function dziFilesTree($root, $options = ['extension' => '.tif', 'prefix' => NULL, 'tile-size' => '256', 'step' => '200', 'skip-confirm' => FALSE, 'threads' => NULL, 'target-uid' => '100', 'target-gid' => '102', 'skip-existing' => FALSE, 'no-pull' => FALSE]) {
    $regex_root = preg_quote($root, '/');

    if (!$options['no-pull']) {
      $this->setPullTilerImage();
    }
    $options['no-pull'] = TRUE;

    if (!empty($options['prefix'])) {
      $glob_path = "$root/{$options['prefix']}*.{$options['extension']}";
      $this->recursiveFiles = glob($glob_path);
    }
    else {
      $regex = "/^{$regex_root}\/[^\/]+\.{$options['extension']}$/i";
      $this->recursiveFileTreeRoot = $root;
      $this->recursiveFileRegex = $regex;
      $this->setFilesToIterate();
      $this->getConfirmFiles('Generate DZI files', $options['skip-confirm']);
    }

    // Remove temporary files from previous runs.
    shell_exec('sudo rm -rf /tmp/dzi/*');

    foreach ($this->recursiveFiles as $file_to_process) {
      $dzi_file_path_info = pathinfo($file_to_process);
      if ($options['skip-existing'] &&
        file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}.dzi") &&
        file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}_files")
      ) {
        $this->say("Skipping file with existing tiles [$file_to_process]");
      }
      else {
        $this->setAddCommandToQueue($this->getDziTileCommand($file_to_process, $options));
      }
    }
    if (!empty($options['threads'])) {
      $this->setThreads($options['threads']);
    }
    $this->setRunProcessQueue('Generate DZI files');
  }

  /**
   * Generate DZI tiles for a specific NBNP issue.
   *
   * @param string $root
   *   The NBNP webtree root file location.
   * @param string $issue_id
   *   The issue entity ID to process.
   *
   * @option threads
   *   The number of threads the process should use.
   * @option skip-existing
   *   Skip any issues with tiles that have been previously generated.
   * @option no-pull
   *   Do not pull docker images prior to running.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:issue:generate-dzi
   */
  public function nbnpDziIssue($root, $issue_id, $options = ['threads' => 1, 'skip-existing' => FALSE, 'no-pull' => FALSE]) {
    $cmd_options = [
      'extension' => 'jpg',
      'tile-size' => '256',
      'prefix' => "{$issue_id}-",
      'step' => '200',
      'skip-confirm' => TRUE,
      'threads' => $options['threads'],
      'target-uid' => '100',
      'target-gid' => '102',
      'skip-existing' => $options['skip-existing'],
      'no-pull' => $options['no-pull'],
    ];
    $this->dziFilesTree($root .'/files/serials/pages', $cmd_options);
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
   * @option no-pull
   *   Do not pull docker images prior to running.
   *
   * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
   *
   * @command dzi:generate-tiles
   */
  public function generateDziFiles($file, $options = ['tile-size' => '256', 'step' => '200', 'target-uid' => '100', 'target-gid' => '102', 'skip-existing' => FALSE, 'no-pull' => FALSE]) {
    $dzi_file_path_info = pathinfo($file);
    if (!file_exists($file)) {
      throw new FileNotFoundException("File $file not Found!");
    }
    if (!$options['skip-existing'] ||
      !file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}.dzi") ||
      !file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}_files")
    ) {
      if (!$options['no-pull']) {
        $this->setPullTilerImage();
      }
      $command = $this->getDziTileCommand($file, $options);
      $command->run();
    }
  }

  /**
   * Pull the docker image required to generate DZI tiles.
   *
   * @command dzi:pull-image
   */
  public function setPullTilerImage() {
    shell_exec("docker pull {$this->imagemagickImage}");
  }

}
