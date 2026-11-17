<?php

namespace UnbLibraries\SystemsToolkit;

/**
 * Trait for interacting with Docker.
 */
trait DockerCleanupTrait {

  /**
   * The label applied to every container the toolkit starts.
   *
   * Cleanup is limited to containers with this label so it never touches
   * other containers, networks or volumes on the host.
   *
   * @return string
   *   The label key.
   */
  public static function getSyskitContainerLabel() : string {
    return 'ca.unb.lib.syskit';
  }

  /**
   * Gets the docker run arguments that label a toolkit container.
   *
   * @return string
   *   The arguments, e.g. '--label ca.unb.lib.syskit=1'.
   */
  public static function getSyskitContainerLabelArgs() : string {
    return '--label ' . self::getSyskitContainerLabel() . '=1';
  }

  /**
   * Removes stopped containers left behind by the toolkit.
   *
   * Toolkit containers run with --rm and create no networks or named
   * volumes, so this only catches containers from interrupted runs. Docker
   * assets belonging to anything else on the host are left alone.
   *
   * @command docker:cleanup
   */
  public function applicationCleanup() : void {
    $this->say("Cleaning up stopped toolkit containers:");
    $this->_exec('docker container prune -f --filter ' . escapeshellarg('label=' . self::getSyskitContainerLabel()));
  }

}
