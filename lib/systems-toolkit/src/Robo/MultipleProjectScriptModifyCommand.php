<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for MultipleProjectBatchStepCommand Robo commands.
 */
class MultipleProjectScriptModifyCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  public const MESSAGE_CLONING_REPO = 'Cloning repository to temporary folder...';
  public const MESSAGE_COMMITTING_CHANGES = 'Committing changes to %s...';
  public const MESSAGE_COPYING_SCRIPT = 'Copying script to clone location...';
  public const MESSAGE_EXCEPTION_SCRIPT_NOT_FOUND = 'The script %s either does not exist, or is not executable.';
  public const MESSAGE_EXECUTING_SCRIPT = 'Executing script...';
  public const MESSAGE_MANUAL_STAGE_ENTER_WHEN_READY = 'Manually stage files, then hit Enter to continue...';
  public const MESSAGE_MANUAL_STAGE_REPO_LOCATION = 'Repository location : %s';
  public const MESSAGE_NO_CHANGES_TO_REPO = 'The script\'s execution did not result in any changes to the repository.';
  public const MESSAGE_NO_STAGED_CHANGES = 'No staged changes were found, skipping commit!';
  public const MESSAGE_PUSHING_CHANGES = 'Pushing repository changes to GitHub...';
  public const MESSAGE_SLEEPING = 'Sleeping for %s seconds to spread build times...';
  public const MESSAGE_STAGING_CHANGES = 'Staging changes in repository...';
  public const MESSAGE_STEP_DONE = 'Done!';
  public const QUESTION_COMMIT_PREFIX_TO_USE = 'Commit prefix to use (i.e. PMPOR-45, Blank for None)? :';
  public const QUESTION_SCRIPT_EXECUTION_OK = 'Script execution complete, commit and push changes?';

  /**
   * The commit message to use for the changes.
   *
   * @var string
   */
  protected string $commitMessage;

  /**
   * The currently cloned repository the service is operating on.
   *
   * @var \UnbLibraries\SystemsToolkit\Git\GitRepo
   */
  protected GitRepo $curCloneRepo;

  /**
   * The current repository the service is operating on.
   *
   * @var array
   */
  protected array $curRepoMetadata;

  /**
   * The base name of the script to run in each repository.
   *
   * @var string
   */
  protected string $modifyingScriptName;

  /**
   * The full path to the script to run in each repository.
   *
   * @var string
   */
  protected string $modifyingScriptFilePath;

  /**
   * Perform, commit changes on multiple repositories with a shell script.
   *
   * Previously used scripts are typically archived in the data folder
   * (/lib/systems-toolkit/data/multiple-modify-scripts) for reference.
   *
   * @param string $match
   *   Only repositories whose names contain one of $match values will be
   *   processed.
   * @param string $topics
   *   Only repositories whose topics contain one of $topics values will be
   *   processed.
   * @param string $commit_message
   *   The issue commit_message.
   * @param string $script_path
   *   The path to the script that makes the changes.
   * @param array $options
   *   An array of CLI options to pass to the command.
   *
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   * @option $manual-file-stage
   *   Do not commit all changes, rather allow the user to manually stage files.
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   * @option $skip-commit-prefix
   *   Do not prompt for a commit prefix when committing.
   * @option $target-branch
   *   The target branch for the changes. Defaults to the default branch.
   *
   * @throws \Exception
   *
   * @command github:multiple-repo:script-modify
   * @usage github:multiple-repo:script-modify '' 'drupal9' 'Config catch-up related to core update 8.x -> 9.x' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateRepoWithProdConfig.sh --yes --skip-commit-prefix --manual-file-stage
   */
  public function setModifyMultipleRepositoriesFromScript(
    string $match,
    string $topics,
    string $commit_message,
    string $script_path,
    array $options = [
      'yes' => FALSE,
      'manual-file-stage' => FALSE,
      'multi-repo-delay' => '240',
      'skip-commit-prefix' => FALSE,
      'target-branch' => '',
    ]
  ) {
    $this->modifyingScriptFilePath = $script_path;
    $this->modifyingScriptName = basename($this->modifyingScriptFilePath);
    $this->commitMessage = $commit_message;
    $this->options = $options;

    $this->checkModifyingScript();
    $continue = $this->setConfirmRepositoryList(
      [$match],
      [$topics],
      [],
      [],
      'Modify Repositories',
      $options['yes']
    );

    if ($continue) {
      foreach ($this->githubRepositories as $this->curRepoMetadata) {
        $this->repoChangesPushed = FALSE;
        $this->syskitIo->title($this->curRepoMetadata['name']);
        $this->cloneTempRepo();
        $this->copyModifyingScript();
        $this->executeModifyingScript();
        if ($this->curCloneRepo->repo->hasChanges()) {
          if ($options['yes'] || $this->confirm(self::QUESTION_SCRIPT_EXECUTION_OK)) {
            $this->stageChangesInRepo();
            // Check for staged changes - may simply be ignoring all changes.
            if (!empty($this->curCloneRepo->repo->execute(['diff', '--cached']))) {
              $this->commitChangesInRepo();
              $this->pushRepositoryChangesToGitHub();
              $this->say(
                sprintf(
                  self::MESSAGE_SLEEPING,
                  $options['multi-repo-delay']
                )
              );
              sleep($options['multi-repo-delay']);
            }
            else {
              $this->say(self::MESSAGE_NO_STAGED_CHANGES);
            }
          }
        }
        else {
          $this->say(self::MESSAGE_NO_CHANGES_TO_REPO);
        }
      }
    }
  }

  /**
   * Checks if the modifying script exists, and can be executed.
   *
   * @throws \Exception
   */
  protected function checkModifyingScript() {
    if (!is_executable($this->modifyingScriptFilePath)) {
      throw new \Exception(
        sprintf(
          self::MESSAGE_EXCEPTION_SCRIPT_NOT_FOUND,
          $this->modifyingScriptFilePath
        )
      );
    }
  }

  /**
   * Clones the current repository to a temporary location.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \Exception
   */
  protected function cloneTempRepo() {
    $this->say(self::MESSAGE_CLONING_REPO);
    $this->curCloneRepo = GitRepo::setCreateFromClone($this->curRepoMetadata['ssh_url'], $this->tmpDir);
    if (!empty($this->options['target-branch'])) {
      $this->curCloneRepo->repo->checkout($this->options['target-branch']);
    }
  }

  /**
   * Copies and prepares the modifying script in the current clone repository.
   *
   * @throws \Exception
   */
  protected function copyModifyingScript() {
    $this->say(self::MESSAGE_COPYING_SCRIPT);
    $git_path = $this->curCloneRepo->getTmpDir();
    $git_script = "$git_path/$this->modifyingScriptName";
    copy($this->modifyingScriptFilePath, $git_script);
    chmod($git_script, 0755);
  }

  /**
   * Executes the modifying script in the current clone repository.
   *
   * @throws \Exception
   */
  protected function executeModifyingScript() {
    $this->say(self::MESSAGE_EXECUTING_SCRIPT);
    passthru("cd {$this->curCloneRepo->getTmpDir()} && ./$this->modifyingScriptName {$this->curRepoMetadata['name']} ; rm -f {$this->modifyingScriptName}");
    $this->say(self::MESSAGE_STEP_DONE);
  }

  /**
   * Stages changes in the current temporary repository.
   *
   * @throws \Exception
   */
  protected function stageChangesInRepo() {
    $this->say(self::MESSAGE_STAGING_CHANGES);
    if ($this->options['manual-file-stage']) {
      $this->say(
        sprintf(
          self::MESSAGE_MANUAL_STAGE_REPO_LOCATION,
          $this->curCloneRepo->getTmpDir()
        )
      );
      $this->ask(self::MESSAGE_MANUAL_STAGE_ENTER_WHEN_READY);
    }
    else {
      $this->curCloneRepo->repo->addAllChanges();
    }
  }

  /**
   * Commits changes in the current temporary repository.
   *
   * @throws \Exception
   */
  protected function commitChangesInRepo() {
    $this->say(
      sprintf(
        self::MESSAGE_COMMITTING_CHANGES,
        $this->curRepoMetadata['name']
      )
    );
    $commit_prefix = '';
    if (!$this->options['skip-commit-prefix']) {
      $commit_prefix = trim($this->ask(self::QUESTION_COMMIT_PREFIX_TO_USE)) . ' ';
    }
    $this->curCloneRepo->repo->commit("$commit_prefix{$this->commitMessage}", ['--no-verify']);
  }

  /**
   * Pushes all commits in the current temporary repository.
   *
   * @throws \Exception
   */
  protected function pushRepositoryChangesToGitHub() {
    $this->say(self::MESSAGE_PUSHING_CHANGES);
    if (!empty($this->options['target-branch'])) {
      $this->curCloneRepo->repo->push('origin', [$this->options['target-branch']]);
    }
    else {
      $this->curCloneRepo->repo->push('origin');
    }
    $this->repoChangesPushed = TRUE;
  }

}
