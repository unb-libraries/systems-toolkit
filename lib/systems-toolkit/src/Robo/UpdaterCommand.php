<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for Updater Robo commands.
 */
class UpdaterCommand extends SystemsToolkitCommand {

  /**
   * Updates composer-based apps on various servers.
   *
   * @throws \Exception
   *
   * @command updater:composer-apps
   */
  public function composerApps() {
    $servers = Robo::Config()->get('syskit.updater.composer-apps');
    foreach ($servers as $server => $apps) {
      foreach ($apps as $app) {
        $this->taskSshExec($server)
          ->exec("cd $app; composer update --prefer-dist")
          ->run();
      }
    }
  }

}
