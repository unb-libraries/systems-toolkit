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

  /**
   * Get the lastest travis build job details or a repository.
   *
   * @param string $repository
   *   The fully namespaced Github repository (unb-libraries/pmportal.org)
   * @param string $branch
   *   The branch of the repository
   *
   * @throws \Exception
   *
   * @command travis:build:get-latest
   *
   * @return string
   *   The build job details, if it exists.
   */
  public function getLatestTravisBuild($repository, $branch) {
    return $this->travisExec($repository, 'show', [$branch], FALSE)->getMessage();
  }

  /**
   * Get the lastest travis build job ID for a repository.
   *
   * @param string $repository
   *   The fully namespaced Github repository (unb-libraries/pmportal.org)
   * @param string $branch
   *   The branch of the repository
   *
   * @throws \Exception
   *
   * @command travis:build:get-latest-id
   *
   * @return string
   *   The job ID, if it exists.
   */
  public function getLatestTravisJobId($repository, $branch) {
    $build_info = $this-> getLatestTravisBuild($repository, $branch);
    preg_match('/Job #([0-9]+)\.[0-9]+\:/', $build_info, $matches);
    if (!empty($matches[1])) {
      return $matches[1];
    }
    return NULL;
  }

}
