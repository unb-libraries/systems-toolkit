<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for BulkDockworkerCommands Robo commands.
 */
class BulkDockworkerCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  const MESSAGE_CHECKING_OUT_REPO = 'Cloning %s repository to temporary folder...';
  const MESSAGE_SLEEPING = 'Sleeping for %s seconds to spread build times...';

  /**
   * The dockworker command string to run.
   *
   * @var string
   */
  protected $commandString = NULL;

  /**
   * The commit message to use.
   *
   * @var string
   */
  protected $commitMessage = NULL;

  /**
   * The name filter to match repositories against.
   *
   * @var string[]
   */
  protected $nameFilter = [];

  /**
   * Command options passed.
   *
   * @var string[]
   */
  protected $options = [];

  /**
   * The tag filter to match repositories against.
   *
   * @var string[]
   */
  protected $tagFilter = [];

  /**
   * Run a dockworker command across several repositories and commit the result.
   *
   * @param string $command_string
   *   The entire dockworker command to run. Quote it!
   * @param string $commit_message
   *   The commit message to use.
   *
   * @option namespaces
   *   The namespaces to apply the commit in. Defaults to dev.
   * @option repo-name
   *   Only perform operations to repository names matching the provided string.
   * @option repo-tag
   *   Only perform operations to repository tags matching the provided string.
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @command github:dockworker:bulk-command
   *
   * @usage github:dockworker:bulk-command 'dockworker:readme:update' '' 'drupal8' 'IN-244 Update Readme Files' --yes
   *
   * @throws \Exception
   */
  public function setDoBulkDockworkerCommands($command_string, $commit_message, $options = ['namespaces' => ['dev'], 'repo-name' => [], 'repo-tag' => [], 'yes' => FALSE, 'multi-repo-delay' => '240']) {
    $this->options = $options;
    $this->commandString = $command_string;
    $this->nameFilter = $options['repo-name'];
    $this->tagFilter = $options['repo-tag'];
    $this->commitMessage = $commit_message;
    $this->runDockworkerCommand();
  }

  /**
   * Run dockworker command against repositories.
   *
   * @throws \Exception
   */
  protected function runDockworkerCommand() {
    $continue = $this->setConfirmRepositoryList(
      $this->nameFilter,
      $this->tagFilter,
      [],
      [],
      'Bulk Dockworker Operations',
      $this->options['yes']
    );

    if ($continue) {
      $this->updateAllRepositories();
    }
  }

  /**
   * Update all queued GitHub repositories.
   *
   * @throws \Exception
   */
  private function updateAllRepositories() {
    foreach($this->githubRepositories as $repository) {
      $this->updateRepository($repository);
      $this->say(
        sprintf(
        self::MESSAGE_SLEEPING,
          $this->options['multi-repo-delay']
        )
      );
      sleep($this->options['multi-repo-delay']);
    }
  }

  /**
   * Update a specific GitHub repository with required updates.
   *
   * @param array $repository
   *   The associative array describing the repository.
   *
   * @throws \Exception
   */
  private function updateRepository(array $repository) {
    $this->say(
      sprintf(
        self::MESSAGE_CHECKING_OUT_REPO,
        $repository['name']
      )
    );
    foreach ($this->options['namespaces'] as $namespace) {
      $repo = GitRepo::setCreateFromClone($repository['ssh_url']);
      $repo->repo->checkout($namespace);
      $repo_path = $repo->repo->getRepositoryPath();
      passthru("cd $repo_path; composer install; ./vendor/bin/dockworker {$this->commandString}; git add .; git commit --no-verify -m '{$this->commitMessage}'; git push origin $namespace");
    }
  }

}
