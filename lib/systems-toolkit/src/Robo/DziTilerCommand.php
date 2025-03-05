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

  const MISSING_DZI_URL = 'https://newspapers.lib.unb.ca/serials_pages/with_missing_dzi';

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
   * Generates any missing DZIs.
   *
   * @param string $root
   *     The filesystem root.
   * @param string $dzi_root
   *     The root location for the DZI files.
   * @param string[] $options
   *     The array of available CLI options.
   *
   * @option $extension
   *     The extensions to match when finding files.
   * @option $no-init
   *     Do not build and pull docker images prior to running.
   * @option $skip-existing
   *     Should images with existing tiles be skipped?
   * @option $target-gid
   *     The gid to assign the target files.
   * @option $target-uid
   *     The uid to assign the target files.
   * @option $threads
   *     The number of threads the process should use.
   * @option $no-cleanup
   *     Do not clean up unused docker assets after running needed containers.
   * @option $limit
   *    The number of files to process.
   * @option $skip
   *    The number of files to skip evaluating.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:generate-missing-dzi
   */
  public function dziFilesMissing(
    string $root,
    string $dzi_root,
    array $options = [
        'extension' => 'jpg',
        'limit' => 50,
        'no-cleanup' => FALSE,
        'no-init' => FALSE,
        'skip-existing' => FALSE,
        'skip' => 0,
        'step' => '200',
        'target-gid' => '102',
        'target-uid' => '100',
        'threads' => NULL,
        'threads' => NULL,
        'tile-size' => '256',
    ]
  )
  {
      $this->setPullTilerImage();

      $options['no-pull'] = TRUE;
      $options['no-cleanup'] = TRUE;

      // Query the website for missing files using guzzle.
      $client = new \GuzzleHttp\Client();
      $limit = $options['limit'];
      $response = $client->request('GET', self::MISSING_DZI_URL . "/$limit/". $options['skip']);

      $missing_files = json_decode($response->getBody()->getContents(), TRUE);
      if (empty($missing_files)) {
        exit("No missing files found.\n");
      }

      shell_exec("sudo rm -rf $this->tmpDir/dzi/*");
      foreach ($missing_files as $missing_file) {
          $this->setAddCommandToQueue(
            $this->getDziTileCommand(
              $root . '/' . $missing_file['rel_image_path'],
              $dzi_root,
              $missing_file['title_id'],
              $missing_file['issue_id'],
              $options
            )
          );
      }

      if (!empty($options['threads'])) {
        $this->setThreads($options['threads']);
      }
      $this->setRunProcessQueue('Generate DZI files');
      $this->applicationCleanup();
      $total_seconds = (int) floor(microtime(TRUE) - $this->commandStartTime);
      $total_time_string = gmdate("H:i:s", $total_seconds);
      $seconds_each = (int) floor($total_seconds / count($missing_files));
      $this->say("Total time: $total_time_string. Average time per file: $seconds_each seconds.");
  }

  /**
   * Generates DZI tiles for an entire tree.
   *
   * @param string $root
   *   The tree root to parse.
   * @param string $dzi_root
   *    The root location for the DZI files.
   * @param string $title_id
   *    The parent digital title ID.
   * @param string $issue_id
   *    The parent digital issue ID.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $extension
   *   The extensions to match when finding files.
   * @option $no-pull
   *   Do not pull docker images prior to running.
   * @option $prefix
   *   The prefix to match when finding files.
   * @option $skip-existing
   *   Should images with existing tiles be skipped?
   * @option $step
   *   The zoom step to use.
   * @option $target-gid
   *   The gid to assign the target files.
   * @option $target-uid
   *   The uid to assign the target files.
   * @option $threads
   *   The number of threads the process should use.
   * @option $tile-size
   *   The tile size to use.
   * @option $no-cleanup
   *   Do not clean up unused docker assets after running needed containers.
   *
   * @throws \Exception
   *
   * @command dzi:generate-tiles:tree
   */
  public function dziFilesTree(
    string $root,
    string $dzi_root,
    string $title_id,
    string $issue_id,
    array $options = [
      'extension' => '.tif',
      'no-pull' => FALSE,
      'prefix' => NULL,
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

    $regex = "/^{$regex_root}\/[^\/]+\.{$options['extension']}$/i";
    $this->recursiveFileTreeRoot = $root;
    $this->recursiveFileRegex = $regex;
    $this->setFilesToIterate();
    $this->getConfirmFiles('Generate DZI files', $options['skip-confirm']);

    // Remove temporary files from previous runs.
    shell_exec("sudo rm -rf $this->tmpDir/dzi/*");

    foreach ($this->recursiveFiles as $file_to_process) {
      $dzi_file_path_info = pathinfo($file_to_process);

      if (!empty($options['prefix'])) {
        $need_process_file = strpos($dzi_file_path_info['filename'], $options['prefix']) === 0;
      }
      else {
        $need_process_file = TRUE;
      }
      if (!$need_process_file) {
        continue;
      }

      if ($options['skip-existing'] &&
        file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}.dzi") &&
        file_exists("{$dzi_file_path_info['dirname']}/{$dzi_file_path_info['filename']}_files")
      ) {
        $this->say("Skipping file with existing tiles [$file_to_process]");
      }
      else {
        $this->setAddCommandToQueue($this->getDziTileCommand($file_to_process, $dzi_root, $title_id, $issue_id, $options));
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
   * @param string $title_id
   *   The issue title ID to process.
   * @param string $issue_id
   *   The issue entity ID to process.
   * @param string $dzi_root
   *   The root location for the DZI files.
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
   * @command newspapers.lib.unb.ca:issue:generate-dzi 97 19347
   *
   * @throws \Exception
   */
  public function nbnpDziIssue(
    string $root,
    string $dzi_root,
    string $title_id,
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
      'prefix' => NULL,
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
      $root . "/$title_id/$issue_id",
      $dzi_root,
      $title_id,
      $issue_id,
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
   * @param string $dzi_root
   *    The root location for the DZI files.
   * @param string $title_id
   *    The parent digital title ID.
   * @param string $issue_id
   *    The parent digital issue ID.
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
    string $dzi_root,
    string $title_id,
    string $issue_id,
    array $options = [
      'step' => '200',
      'target-gid' => '102',
      'target-uid' => '100',
      'tile-size' => '256',
    ]
  ) : CommandInterface {
    $dzi_file_path_info = pathinfo($file);
    $tmp_dir = "$this->tmpDir/dzi/{$dzi_file_path_info['filename']}";
    $target_dir = $dzi_root . "/$title_id/$issue_id";

    return $this->taskExecStack()
      ->stopOnFail()
      ->exec("sudo rm -rf $tmp_dir")
      ->exec("mkdir -p $tmp_dir")
      ->exec("cp $file $tmp_dir")
      ->exec("docker run -v  $tmp_dir:/data --rm {$this->imagemagickImage} /app/magick-slicer.sh -- -e jpg -i /data/{$dzi_file_path_info['basename']} -o /data/{$dzi_file_path_info['filename']} --dzi -s {$options['step']} -w {$options['tile-size']} -h {$options['tile-size']}")
      ->exec("mkdir -p $target_dir")
      ->exec("sudo cp -r $tmp_dir/{$dzi_file_path_info['filename']}_files $target_dir/")
      ->exec("sudo chown {$options['target-uid']}:{$options['target-gid']} -R $target_dir/{$dzi_file_path_info['filename']}_files")
      ->exec("sudo cp $tmp_dir/{$dzi_file_path_info['filename']}.dzi $target_dir/")
      ->exec("sudo chown {$options['target-uid']}:{$options['target-gid']} $target_dir/{$dzi_file_path_info['filename']}.dzi")
      ->exec("sudo rm -rf $tmp_dir");
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
