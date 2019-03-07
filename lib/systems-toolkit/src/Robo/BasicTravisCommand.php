<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\TravisExecTrait;

/**
 * Class for BasicTravisCommand Robo commands.
 */
class BasicTravisCommand extends SystemsToolkitCommand {

  use TravisExecTrait;

  /**
   * Restart a travis build job.
   *
   * @param string $repository
   *   The fully namespaced Github repository (unb-libraries/pmportal.org)
   * @param string $build_id
   *   The job ID
   *
   * @throws \Exception
   *
   * @command travis:build:restart
   */
  public function restartTravisBuild($repository, $build_id) {
    $this->travisExec($repository, 'show', 'dev');
  }

}
