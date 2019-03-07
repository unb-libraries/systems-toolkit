<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;

/**
 * Class for TravisExecTrait.
 */
trait TravisExecTrait {

  /**
   * The path to the travis binary.
   *
   * @var string
   */
  protected $travisBin;

  /**
   * The current repositories to exec commands in.
   *
   * @var object[]
   */
  protected $travisCurRepos = [];

  /**
   * Get travis CLI binary path from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setTravisBin() {
    $this->travisBin = Robo::Config()->get('syskit.travis.bin');
    if (empty($this->travisBin)) {
      throw new \Exception(sprintf('The travis binary path is unset in %s', $this->configFile));
    }
  }

  /**
   * Execute a travis command via the CLI.
   *
   * @param string $repository
   *   The fully namespaced Github repository (unb-libraries/pmportal.org)
   * @param string $command
   *   The command to execute (i.e. ls)
   * @param string[] $args
   *   A list of arguments to pass to the command.
   * @param bool $print_output
   *   TRUE if the command should output results. False otherwise.
   *
   * @return \Robo\ResultData
   *   The result of the execution.
   */
  private function travisExec($repository, $command, $args = [], $print_output = TRUE) {
    $travis = $this->taskExec($this->travisBin)
      ->printOutput($print_output)
      ->arg($command)
      ->arg("--repo=$repository");

    if (!empty($args)) {
      foreach ($args as $arg) {
        $travis->arg($arg);
      }
    }
    $this->say(sprintf('Executing travis %s in %s...', $command, $repository));
    return $travis->run();
  }

}
