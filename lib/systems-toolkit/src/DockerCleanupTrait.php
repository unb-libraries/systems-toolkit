<?php

namespace UnbLibraries\SystemsToolkit;

/**
 * Trait for interacting with Docker.
 */
trait DockerCleanupTrait {

  /**
   * Cleans up any docker assets not being used.
   *
   * @command docker:cleanup
   */
  public function applicationCleanup() : void {
    $this->say("Cleaning up dangling containers and volumes:");
    $this->_exec('docker network prune -f');
    $this->_exec('docker volume prune -f');
    $this->_exec('docker container prune -f');
  }

}
