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

  /**
   * List all repositories that contain a specific file.
   *
   * @param string $file_path
   *   The path to the file to query.
   * @param string $name_filter
   *   The name filter to apply when choosing the repositories to operate on.
   * @param string $tag_filter
   *   The tag filter to apply when choosing the repositories to operate on.
   * @param string $branch
   *   The repository branch to query. Defaults to 'dev'.
   *
   * @command repository-list:contains-file
   *
   * @usage repository-list:contains-file config-yml/samlauth.authentication.yml '' drupal8
   */
  public function listContainsFileRepositories($file_path, $name_filter, $tag_filter, $branch = 'dev') {
    $this->setRepositoryList(
      [$name_filter],
      [$tag_filter],
      [],
      []
    );
    foreach ($this->githubRepositories as $idx => $repository) {
      if (!$this->client->api('repo')->contents()->exists($repository['owner']['login'], $repository['name'], $file_path, $branch)) {
        unset($this->githubRepositories[$idx]);
      }
    }
    $this->repositoryListDisplay("[$name_filter|$tag_filter] Repositories containing file $file_path");
  }

}
