<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;

/**
 * Trait for interacting with Docker.
 */
trait DockerCommandTrait {

  /**
   * Clean up any leftover docker assets not being used.
   *
   * @command docker:cleanup
   *
   * @hook post-init
   */
  public function applicationCleanup() {
    $this->say("Cleaning up dangling images and volumes:");
    $this->_exec('docker images -qf dangling=true | xargs docker rmi -f');
    $this->_exec('docker volume ls -qf dangling=true | xargs docker volume rm');
  }

}
