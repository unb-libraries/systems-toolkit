<?php

namespace UnbLibraries\SystemsToolkit\Robo;

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

  const CONFIG_FILENAME = 'syskit_config.yml';
  const ERROR_CONFIG_MISSING = 'The config file was not found. Please copy %s.sample to %s and add your values.';

  /**
   * The path to the configuration file.
   *
   * @var string
   */
  protected $configFile;

  /**
   * The path to the Syskit repo.
   *
   * @var string
   */
  protected $repoRoot;

  /**
   * Constructor.
   */
  public function __construct() {
    // Read configuration.
    $this->repoRoot = realpath(__DIR__ . "/../../../../");
    $this->configFile = self::CONFIG_FILENAME;
    Robo::loadConfiguration(
      [$this->repoRoot . '/' . $this->configFile]
    );
  }

  /**
   * Check if the configuration file exists.
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

}
