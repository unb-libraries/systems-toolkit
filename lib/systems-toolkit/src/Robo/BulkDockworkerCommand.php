<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for BulkDockworkerCommands Robo commands.
 */
class BulkDockworkerCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  public const MESSAGE_CHECKING_OUT_REPO = 'Cloning %s repository to temporary folder...';
  public const MESSAGE_SLEEPING = 'Push detected - sleeping for %s seconds to spread build times...';

  /**
   * The dockworker command string to run.
   *
   * @var string
   */
  protected string $commandString;

  /**
   * The commit message to use.
   *
   * @var string
   */
  protected string $commitMessage;

  /**
   * The name filter to match repositories against.
   *
   * @var string[]
   */
  protected array $nameFilter = [];

  /**
   * The tag filter to match repositories against.
   *
   * @var string[]
   */
  protected array $tagFilter = [];

  /**
   * Executes a command in dockworker repos and pushes any changes to GitHub.
   *
   * @param string $command_string
   *   The dockworker command to run within each repo. Quote it!
   * @param string $commit_message
   *   The commit message to use if file changes occur.
   * @param array $options
   *   An array of CLI options to pass to the command.
   *
   * @option $branch
   *   Sets the git branch to operate on.
   * @option $repo-name
   *   Filter: Only operate on repos with names matching the provided string.
   * @option $repo-tag
   *   Only perform operations to repository tags the provided string.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @command github:dockworker:bulk-command
   * @usage 'readme:update' '' 'drupal8' 'IN-244 Update Readme Files' --yes
   *
   * @throws \Exception
   */
  public function setDoBulkDockworkerCommands(
    string $command_string,
    string $commit_message,
    array $options = [
      'branch' => ['dev'],
      'repo-name' => [],
      'repo-tag' => [],
      'yes' => FALSE,
      'multi-repo-delay' => self::DEFAULT_MULTI_REPO_DELAY,
    ]
  ) {
    $this->options = $options;
    $this->commandString = $command_string;
    $this->nameFilter = $options['repo-name'];
    $this->tagFilter = $options['repo-tag'];
    $this->commitMessage = $commit_message;
    $this->runDockworkerCommand();
  }

  /**
   * Runs a dockworker command against repositories.
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
   * Updates all queued GitHub repositories.
   *
   * @throws \Exception
   */
  private function updateAllRepositories() {
    $last_repo_key = array_key_last($this->githubRepositories);
    foreach ($this->githubRepositories as $repository_index => $repository) {
      $this->io()->title($repository['name']);
      $this->updateRepository($repository);
      if ($this->repoChangesPushed && $repository_index != $last_repo_key) {
        $this->io()->note(
          sprintf(
            self::MESSAGE_SLEEPING,
            $this->options['multi-repo-delay']
          )
        );
        sleep($this->options['multi-repo-delay']);
      }
      else {
        $this->io()->note("Command [$this->commandString] resulted in no changes!");
      }
      $this->io()->newLine();
    }
  }

  /**
   * Updates a specific GitHub repository with required updates.
   *
   * @param array $repository
   *   The associative array describing the repository.
   *
   * @throws \Exception
   */
  private function updateRepository(array $repository) {
    $this->repoChangesPushed = FALSE;
    $this->io()->note(
      sprintf(
        self::MESSAGE_CHECKING_OUT_REPO,
        $repository['name']
      )
    );

    foreach ($this->options['namespaces'] as $namespace) {
      $repo = GitRepo::setCreateFromClone($repository['ssh_url'], $this->tmpDir);
      $repo->repo->checkout($namespace);
      $repo_path = $repo->repo->getRepositoryPath();
      $this->io()->note('Installing Dockworker...');
      passthru("cd $repo_path; composer install");
      $this->io()->note("Running /vendor/bin/dockworker {$this->commandString}...");
      passthru("cd $repo_path; ./vendor/bin/dockworker {$this->commandString};");
      if ($repo->repo->hasChanges()) {
        $this->io()->note('Updates found, committing...');
        $repo->repo->addAllChanges();
        $repo->repo->commit($this->commitMessage, ['--no-verify']);
        $this->io()->note('Pushing Changes to GitHub...');
        $repo->repo->push(['origin', $namespace]);
        $this->repoChangesPushed = TRUE;
      }
    }
  }

}
