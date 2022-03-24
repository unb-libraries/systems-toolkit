<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Common\ConfigAwareTrait;
use Robo\Robo;
use Robo\Tasks;

/**
 * Base class for SystemsToolkit Robo commands.
 */
class SystemsToolkitCommand extends Tasks implements LoggerAwareInterface {

  use ConfigAwareTrait;
  use LoggerAwareTrait;

  public const CONFIG_FILENAME = 'syskit_config.yml';
  public const ERROR_CONFIG_MISSING = 'The config file was not found. Please copy %s.sample to %s and add your values.';

  /**
   * The start time of the command.
   *
   * @var string
   */
  protected $commandStartTime;

  /**
   * The path to the configuration file.
   *
   * @var string
   */
  protected $configFile;

  /**
   * The current command options.
   *
   * @var array
   */
  protected $options;

  /**
   * The path to the Syskit repo.
   *
   * @var string
   */
  protected $repoRoot;

  /**
   * The temporary directory to use, if necessary.
   *
   * @var string
   */
  protected $tmpDir;

  /**
   * Constructor.
   */
  public function __construct() {
    // Read configuration.
    $this->commandStartTime = microtime(TRUE);
    $this->repoRoot = realpath(__DIR__ . "/../../../../");
    $this->configFile = self::CONFIG_FILENAME;
    Robo::loadConfiguration(
      [$this->repoRoot . '/' . $this->configFile]
    );
  }

  /**
   * Check if the configuration file exists.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function checkConfigExists() {
    if (!file_exists($this->repoRoot . '/' . $this->configFile)) {
      throw new \Exception(
        sprintf(
          self::ERROR_CONFIG_MISSING,
          $this->configFile,
          $this->configFile
        )
      );
    }
  }

  /**
   * Sets up the temporary directory to be used by commands.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function setSyskitTmpDir() {
    $tmp_dir_root = Robo::Config()->get('syskit.local.tmpdir');
    if (!empty($tmp_dir_root)) {
      $this->tmpDir = $tmp_dir_root;
    }
    else {
      $this->tmpDir = sys_get_temp_dir();
    }
  }

  /**
   * Runs another System Toolkit command.
   *
   * This is necessary until the annotated-command feature request:
   * https://github.com/consolidation/annotated-command/issues/64 is merged
   * or solved. Otherwise hooks do not fire as expected.
   *
   * @param string $command_string
   *   The Dockworker command to run.
   * @param string $exception_message
   *   The message to display if a non-zero code is returned.
   *
   * @throws \Exception
   *
   * @return int
   *   The return code of the command.
   */
  public function setRunOtherCommand($command_string, $exception_message = NULL) {
    $this->io()->note("Spawning new command thread: $command_string");
    $bin = $_SERVER['argv'][0];
    $command = "$bin --ansi $command_string";
    passthru($command, $return);

    if ($return > 0) {
      throw new Exception($exception_message);
    }
    return $return;
  }

}
