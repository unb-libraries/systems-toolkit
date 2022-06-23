<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for RepositoryListCommand Robo commands.
 */
class RepositoryListCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * Lists all dockworker repositories.
   *
   * @throws \Exception
   *
   * @command repository-list:dockworker
   */
  public function listDockworkerRepositories() : void {
    $this->setRepositoryList(
      [],
      ['dockworker'],
      [],
      []
    );
    $this->repositoryListDisplay('Dockworker Repositories:');
  }

  /**
   * Lists all Drupal repositories.
   *
   * @throws \Exception
   *
   * @command repository-list:drupal
   */
  public function listDrupalRepositories() : void {
    $this->setRepositoryList(
      [],
      ['drupal8', 'drupal9'],
      [],
      []
    );
    $this->repositoryListDisplay('Drupal Repositories:');
  }

  /**
   * Displays a list of selected repositories.
   *
   * @param string $title
   *   The string to use as the title.
   */
  protected function repositoryListDisplay(string $title) : void {
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
   * Lists all repositories that contain a specific file.
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
   * @usage repository-list:contains-file config-yml/samlauth.authentication.yml '' drupal8
   */
  public function listContainsFileRepositories(
    string $file_path,
    string $name_filter,
    string $tag_filter,
    string $branch = 'dev'
  ) : void {
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
