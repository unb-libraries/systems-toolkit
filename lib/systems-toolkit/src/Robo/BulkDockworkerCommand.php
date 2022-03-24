<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
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
  protected $commandString;

  /**
   * The commit message to use.
   *
   * @var string
   */
  protected $commitMessage;

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
   * @param array $options
   *   An array of CLI options to pass to the command.
   *
   * @option $namespaces
   *   The namespaces to apply the commit in. Defaults to dev.
   * @option $repo-name
   *   Only perform operations to repository names matching the provided string.
   * @option $repo-tag
   *   Only perform operations to repository tags matching the provided string.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @command github:dockworker:bulk-command
   *
   * @usage github:dockworker:bulk-command 'readme:update' '' 'drupal8' 'IN-244 Update Readme Files' --yes
   *
   * @throws \Exception
   */
  public function setDoBulkDockworkerCommands(
    $command_string,
    $commit_message,
    array $options = [
      'namespaces' => ['dev'],
      'repo-name' => [],
      'repo-tag' => [],
      'yes' => FALSE,
      'multi-repo-delay' => '240',
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
    foreach ($this->githubRepositories as $repository) {
      $this->io()->title($repository['name']);
      $this->updateRepository($repository);
      if ($this->repoChangesPushed) {
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
   * Update a specific GitHub repository with required updates.
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
        $repo->repo->push('origin', [$namespace]);
        $this->repoChangesPushed = TRUE;
      }
    }
  }

}
