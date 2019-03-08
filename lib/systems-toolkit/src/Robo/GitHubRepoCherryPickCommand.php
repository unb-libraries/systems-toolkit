<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for GitHubRepoCherryPickCommand Robo commands.
 */
class GitHubRepoCherryPickCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  const ERROR_MISSING_REPOSITORY = 'The repository [%s] was not found in any of your configured organizations.';
  const MESSAGE_BEGINNING_CHERRY_PICK = 'Starting cherry pick operation from [%s] onto all repositories matching topics [%s] and name [%s]';
  const MESSAGE_CHERRY_PATCH_FAILED = 'Patch cannot apply to [%s/%s]';
  const MESSAGE_CHERRY_PATCH_SUCCESS = 'Patch successfully applied to [%s/%s]';
  const MESSAGE_CHERRY_PICKING = 'Cherry picking [%s] onto [%s/%s]...';
  const MESSAGE_CHERRY_RESULTS_TITLE = 'Output from cherry-pick operation:';
  const MESSAGE_CHOOSE_COMMIT_HASH = 'What commit hash should be cherry-picked onto other repositories';
  const MESSAGE_CHOOSE_TARGET_BRANCH = 'What branch on the other repositories should receive the commit?';
  const MESSAGE_CONFIRM_PUSH = 'Was the cherry-pick clean? Still want to push to GitHub?';
  const MESSAGE_PUSH_RESULTS_TITLE = 'Push Results:';
  const MESSAGE_REFUSING_CHERRY_ALL_REPOSITORIES = 'Cowardly refusing to cherry pick onto all repositories on GitHub. Please include a $match argument or $topics. If you are having issues, try github:repo:cherry-pick-multiple --help';
  const MESSAGE_TARGET_BRANCH_MISSING_REPO = 'The target branch %s is missing from the [%s] repository. Skipping!';
  const MESSAGE_TITLE_REPO_COMMIT_LIST = 'Most recent commits in [%s]:';
  const OPERATION_TYPE = 'cherry pick a commit from %s until other repositories';

  /**
   * The source repository array.
   *
   * @var array
   */
  protected $sourceRepo;

  /**
   * Cherry pick a commit from a repo onto multiple others. Robo Command.
   *
   * @param string $source_repository
   *   The name of the repository to source the commit from.
   * @param string $target_topics
   *   A comma separated list of topics to match. Only repositories whose
   *   topics contain at least one of the comma separated values exactly will
   *   have the commit picked onto. Optional.
   * @param string $target_name_match
   *   A comma separated list of names to match. Only repositories whose names
   *   partially match at least one of the comma separated values will have the
   *   commit picked onto. Optional.
   *
   * @throws \Exception
   *
   * @usage pmportal.org drupal8 unbherb
   *
   * @command github:repo:cherry-pick-multiple
   */
  public function cherryPickMultiple($source_repository, $target_topics = '', $target_name_match = '') {
    $match_array = explode(",", $target_name_match);
    $topics_array = explode(",", $target_topics);

    if (empty($match_array[0]) && empty($topics_array[0])) {
      $this->say(self::MESSAGE_REFUSING_CHERRY_ALL_REPOSITORIES);
      return;
    }

    $this->cherryPickOneToMultiple(
      $source_repository,
      $topics_array,
      $match_array
    );
  }

  /**
   * Cherry pick a commit from a repo onto multiple others.
   *
   * @param string $source_repository
   *   The name of the repository to source the commit from.
   * @param array $target_topics
   *   An array of topics to match. Only repositories whose topics contain at
   *   least one of the values exactly will have the commit picked onto.
   *   Optional.
   * @param array $target_name_match
   *   An array of names to match. Only repositories whose names partially match
   *   at least one of the values will have the commit picked onto. Optional.
   *
   * @throws \Exception
   */
  protected function cherryPickOneToMultiple($source_repository, array $target_topics = [], array $target_name_match = []) {
    $this->say(
      sprintf(
        self::MESSAGE_BEGINNING_CHERRY_PICK,
        $source_repository,
        implode(',', $target_topics),
        implode(',', $target_name_match)
      )
    );

    // Verify repository exists.
    $this->sourceRepo = $this->getRepositoryExists($source_repository);

    // Instantiate local source repo.
    $source_repo = GitRepo::setCreateFromClone($this->sourceRepo['ssh_url']);

    // Ask which Commit to Rebase.
    $this->say(sprintf(self::MESSAGE_TITLE_REPO_COMMIT_LIST, $source_repository));
    $this->getCommitListTable($source_repo, 10);
    $cherry_hash = $this->askDefault(self::MESSAGE_CHOOSE_COMMIT_HASH, $source_repo->getCommit(0)['hash']);
    $cherry_commit_msg = $source_repo->getCommitMessage($cherry_hash);

    // Verify commit is in repo and release local source repo.
    $this->getRepoHasCommit($source_repo, $cherry_hash);
    unset($source_repo);

    // Get repositories.
    $continue = $this->setConfirmRepositoryList(
      $target_name_match,
      $target_topics,
      [],
      [$source_repository],
      sprintf(
        self::OPERATION_TYPE,
        $source_repository
      )
    );

    // Cherry-Pick and push up to GitHub.
    if ($continue) {
      // Ask what branch commit should be cherry-picked to.
      $target_branch = $this->askDefault(self::MESSAGE_CHOOSE_TARGET_BRANCH, 'dev');

      foreach ($this->githubRepositories as $repository_data) {
        // Check to see if this repo has the target branch.
        if (!$this->getGitHubRepositoryHasBranch($repository_data['owner']['login'], $repository_data['name'], $target_branch)) {
          $this->say(
            sprintf(self::MESSAGE_TARGET_BRANCH_MISSING_REPO, $target_branch, $repository_data['name'])
          );
          $this->failedRepos[$repository_data['name']] = "$target_branch branch does not exist";
          continue;
        };

        $this->say(
          sprintf(self::MESSAGE_CHERRY_PICKING,
            $cherry_hash,
            $repository_data['name'],
            $target_branch
          )
        );
        $target_repo = GitRepo::setCreateFromClone($repository_data['ssh_url']);
        $target_repo->repo->checkout($target_branch);
        $cherry_output = [];
        $cherry_output = $cherry_output + $target_repo->repo->execute(
          [
            'remote',
            'add',
            $source_repository,
            $this->sourceRepo['ssh_url'],
          ]
        );
        $cherry_output = $cherry_output + $target_repo->repo->execute(
          [
            'fetch',
            '--all',
          ]
        );
        $cherry_output = $cherry_output + $target_repo->repo->execute(
          [
            'format-patch',
            '-n1',
            $cherry_hash,
          ]
        );

        // Apply patch.
        exec("cd {$target_repo->getTmpDir()} && git apply --index 0001*.patch", $output, $return);
        if ($return) {
          $this->say(sprintf(self::MESSAGE_CHERRY_PATCH_FAILED, $target_branch, $repository_data['name']));
          $this->failedRepos[$repository_data['name']] = "Patch could not be applied.";
          continue;
        }
        $this->say(sprintf(self::MESSAGE_CHERRY_PATCH_SUCCESS, $target_branch, $repository_data['name']));

        $cherry_output = $cherry_output + $output;
        $cherry_output = $cherry_output + $target_repo->repo->execute(
            [
              'commit',
              '--no-gpg-sign',
              '-m',
              $cherry_commit_msg,
            ]
          );

        $this->say(self::MESSAGE_CHERRY_RESULTS_TITLE);
        $this->say(implode("\n", $cherry_output));

        // Push.
        $continue = $this->confirm(self::MESSAGE_CONFIRM_PUSH);
        if ($continue) {
          $push_output = $target_repo->repo->execute(
            [
              'push',
              'origin',
              $target_branch,
            ]
          );
          $this->say(self::MESSAGE_PUSH_RESULTS_TITLE);
          $this->say(implode("\n", $push_output));
        }
        $this->successfulRepos[$repository_data['name']] = "Success.";
      }
    }
  }

}
