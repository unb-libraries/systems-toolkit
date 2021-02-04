<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\GitHubMultipleInstanceTrait;

/**
 * Class for RepositoryListCommand Robo commands.
 */
class RepositoryListCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * List all dockworker repositories.
   *
   * @throws \Exception
   *
   * @command repository-list:dockworker
   */
  public function listDockworkerRepositories() {
    $this->setRepositoryList(
      [],
      ['dockworker'],
      [],
      []
    );
    $this->repositoryListDisplay('Dockworker Repositories:');
  }

  /**
   * List all D8 repositories.
   *
   * @throws \Exception
   *
   * @command repository-list:drupal8
   */
  public function listDrupalEightRepositories() {
    $this->setRepositoryList(
      [],
      ['drupal8'],
      [],
      []
    );
    $this->repositoryListDisplay('Drupal 8 Repositories:');
  }

  protected function repositoryListDisplay($title) {
    if (empty($this->githubRepositories)) {
      $this->say('No repositories found!');
      return;
    }
    $this->io()->title($title);
    foreach ($this->githubRepositories as $repository) {
      $this->io()->writeln($repository['name']);
    }
  }

}
