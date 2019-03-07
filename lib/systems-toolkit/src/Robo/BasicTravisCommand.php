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
   *   The fully namespaced Github repository (i.e. unb-libraries/pmportal.org)
   * @param string $build_id
   *   The build job ID
   *
   * @throws \Exception
   *
   * @command travis:build:restart
   *
   * @return \Robo\ResultData
   */
  public function restartTravisBuild($repository, $build_id) {
    return $this->travisExec($repository, 'restart', [$build_id]);
  }

  /**
   * Restart the latest travis build job in a branch of a repository.
   *
   * @param string $repository
   *   The fully namespaced Github repository (i.e. unb-libraries/pmportal.org)
   * @param string $branch
   *   The branch
   *
   * @throws \Exception
   *
   * @command travis:build:restart-latest
   *
   * @return \Robo\ResultData
   */
  public function restartLatestTravisBuild($repository, $branch) {
    $latest_build_id = $this->getLatestTravisJobId($repository, $branch);
    return $this->restartTravisBuild($repository, $latest_build_id);
  }

}
