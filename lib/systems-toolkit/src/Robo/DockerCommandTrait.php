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
    $this->say("Cleaning up dangling containers and volumes:");
    $this->_exec('docker network prune -af');
    $this->_exec('docker volume prune -af');
    $this->_exec('docker container prune -af');
  }

}
