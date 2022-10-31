<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Contract\CommandInterface;
use Robo\Robo;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use UnbLibraries\SystemsToolkit\DockerCleanupTrait;
use UnbLibraries\SystemsToolkit\QueuedParallelExecTrait;
use UnbLibraries\SystemsToolkit\RecursiveFileTreeTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for DziTilerCommand Robo commands.
 */
class DziTilerCommand extends SystemsToolkitCommand {

  use DockerCleanupTrait;
  use QueuedParallelExecTrait;
  use RecursiveFileTreeTrait;

  /**
   * The docker image to use for Imagemagick commands.
   *
   * @var string
   */
  private string $imagemagickImage;

  /**
   * Gets the Tesseract docker image from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setImagingImage() : void {
    $this->imagemagickImage = Robo::Config()->get('syskit.imaging.imagemagickImage');
    if (empty($this->imagemagickImage)) {
      throw new \Exception(sprintf('The imagemagick docker image has not been set in the configuration file. (imagemagickImage)'));
    }
  }

  /**
   * Generates DZI tiles for an entire tree.
   *
   * @param string $root
   *   The tree root to parse.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $extension
   *   The extensions to match when finding files.
   * @option $no-pull
   *   Do not pull docker images prior to running.
   * @option $tile-size
   *   The tile size to use.
   * @option $step
   *   The zoom step to use.
   * @option $threads
   *   The number of threads the process should use.
   * @option $args
   *   Any other arguments to pass.
   * @option $target-uid
   *   The uid to assign the target files.
   * @option $target-gid
   *   The gid to assign the target files.
   * @option $no-cleanup
   *   Do not clean up unused docker assets after running needed containers.
   *
   * @throws \Exception
   *
   * @command dzi:generate-tiles:tree
   */
  public function dziFilesTree(
    string $root,
    array $options = [
      'extension' => '.tif',
      'no-pull' => FALSE,
      'prefix' => NULL,
      'skip-confirm' => FALSE,
      'skip-existing' => FALSE,
      'step' => '200',
      'target-gid' => '102',
      'target-uid' => '100',
      'threads' => NULL,
      'tile-size' => '256',
      'no-cleanup' => FALSE,
    ]
  ) : void {
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
    shell_exec("sudo rm -rf $this->tmpDir/dzi/*");

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
    if (!$options['no-cleanup']) {
      $this->applicationCleanup();
    }
  }

  /**
   * Generates DZI tiles for a specific NBNP issue.
   *
   * @param string $root
   *   The NBNP webtree root file location.
   * @param string $issue_id
   *   The issue entity ID to process.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $no-cleanup
   *   Do not clean up unused docker assets after running.
   * @option $no-pull
   *   Do not pull docker images prior to running.
   * @option $skip-existing
   *   Skip any issues with tiles that have been previously generated.
   * @option $threads
   *   The number of threads the process should use.
   *
   * @command newspapers.lib.unb.ca:issue:generate-dzi
   *
   * @throws \Exception
   */
  public function nbnpDziIssue(
    string $root,
    string $issue_id,
    array $options = [
      'no-cleanup' => FALSE,
      'no-pull' => FALSE,
      'skip-existing' => FALSE,
      'threads' => 1,
    ]
  ) : void {
    $cmd_options = [
      'extension' => 'jpg',
      'no-pull' => $options['no-pull'],
      'prefix' => "{$issue_id}-",
      'skip-confirm' => TRUE,
      'skip-existing' => $options['skip-existing'],
      'step' => '200',
      'target-gid' => '102',
      'target-uid' => '100',
      'threads' => $options['threads'],
      'tile-size' => '256',
      'no-cleanup' => TRUE,
    ];
    $this->dziFilesTree(
      $root . '/files/serials/pages',
      $cmd_options
    );
    if (!$options['no-cleanup']) {
      $this->applicationCleanup();
    }
  }

  /**
   * Generates the Robo command used to generate the DZI tiles.
   *
   * @param string $file
   *   The file to parse.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $tile-size
   *   The tile size to use.
   * @option $step
   *   The zoom step to use.
   * @option $target-uid
   *   The uid to assign the target files.
   * @option $target-gid
   *   The gid to assign the target files.
   *
   * @return \Robo\Contract\CommandInterface
   *   The Robo command, ready to execute.
   */
  private function getDziTileCommand(
    string $file,
    array $options = [
      'step' => '200',
      'target-gid' => '102',
      'target-uid' => '100',
      'tile-size' => '256',
    ]
  ) : CommandInterface {
    $dzi_file_path_info = pathinfo($file);
    $tmp_dir = "$this->tmpDir/dzi/{$dzi_file_path_info['filename']}";

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
   * Generates the DZI tiles for a file.
   *
   * @param string $file
   *   The file to parse.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $tile-size
   *   The tile size to use.
   * @option $step
   *   The zoom step to use.
   * @option $target-uid
   *   The uid to assign the target files.
   * @option $target-gid
   *   The gid to assign the target files.
   * @option $no-pull
   *   Do not pull docker images prior to running.
   *
   * @throws \Symfony\Component\Filesystem\Exception\FileNotFoundException
   *
   * @command dzi:generate-tiles
   */
  public function generateDziFiles(
    string $file,
    array $options = [
      'no-pull' => FALSE,
      'skip-existing' => FALSE,
      'step' => '200',
      'target-gid' => '102',
      'target-uid' => '100',
      'tile-size' => '256',
    ]
  ) : void {
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
   * Pulls the docker image required to generate DZI tiles.
   *
   * @command dzi:pull-image
   */
  public function setPullTilerImage() : void {
    shell_exec("docker pull {$this->imagemagickImage}");
  }

}
