<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitGitHubMultipleInstanceCommand;

/**
 * Base class for SystemsToolkitGitHubRepoRebaseDevToProdCommand.
 */
class SystemsToolkitGitHubRepoRebaseDevToProdCommand extends SystemsToolkitGitHubMultipleInstanceCommand {

  const MESSAGE_CHECKING_OUT_REPO = 'Cloning %s repository to temporary folder...';
  const MESSAGE_CONFIRM_PUSH = 'Was the rebase clean? Still want to push to GitHub?';
  const MESSAGE_PUSH_RESULTS_TITLE = 'Push Results:';
  const MESSAGE_REBASE_RESULTS_TITLE = 'Rebase Results:';
  const MESSAGE_REBASING = 'Rebasing %s onto %s...';
  const MESSAGE_REFUSING_REBASE_ALL_REPOSITORIES = 'Cowardly refusing to rebase all repositories on GitHub. Please include a $match argument or $topics. If you are having issues, try github:repo:rebasedevprod --help';
  const OPERATION_TYPE = 'REBASE %s ONTO %s';
  const UPMERGE_SOURCE_BRANCH = 'dev';
  const UPMERGE_TARGET_BRANCH = 'prod';

  /**
   * Rebase dev onto prod for one or multiple GitHub repositories.
   *
   * @param array $match
   *   Only repositories whose names contain one of $match values will be
   *   processed. Optional.
   * @param array $topics
   *   Only repositories whose topics contain one of $topics values will be
   *   processed. Optional.
   */
  protected function rebaseDevToProd(array $match = [], array $topics = []) {
    // Get repositories.
    $continue = $this->setConfirmRepositoryList(
      $match,
      $topics,
      [],
      [],
      sprintf(
        self::OPERATION_TYPE,
        self::UPMERGE_SOURCE_BRANCH,
        self::UPMERGE_TARGET_BRANCH
      )
    );

    // Rebase and push up to GitHub.
    if ($continue) {
      foreach ($this->repositories as $repository_data) {
        // Pull down.
        $this->say(
          sprintf(
            self::MESSAGE_CHECKING_OUT_REPO,
            $repository_data['name']
          )
        );
        $repo = GitRepo::setCreateFromClone($repository_data['ssh_url']);

        // Rebase.
        $repo->repo->checkout('prod');
        $this->say(
          sprintf(self::MESSAGE_REBASING,
            self::UPMERGE_SOURCE_BRANCH,
            self::UPMERGE_TARGET_BRANCH
          )
        );
        $rebase_output = $repo->repo->execute(
          [
            'rebase',
            self::UPMERGE_SOURCE_BRANCH,
          ]
        );
        $this->say(self::MESSAGE_REBASE_RESULTS_TITLE);
        $this->say(implode("\n", $rebase_output));

        // Push.
        $continue = $this->confirm(self::MESSAGE_CONFIRM_PUSH);
        if ($continue) {
          $push_output = $repo->repo->execute(
            [
              'push',
              'origin',
              self::UPMERGE_TARGET_BRANCH,
            ]
          );
          $this->say(self::MESSAGE_PUSH_RESULTS_TITLE);
          $this->say(implode("\n", $push_output));
        }
      }
    }
  }

  /**
   * Rebase dev onto prod for one or multiple GitHub Repositories.
   *
   * This command will rebase all commits that exist in the dev branch of a
   * GitHub repository onto the prod branch.
   *
   * @param string $match
   *   A comma separated list of names to match. Only repositories whose names
   *   partially match at least one of the comma separated values will be
   *   processed. Optional.
   * @param string $topics
   *   A comma separated list of topics to match. Only repositories whose
   *   topics contain at least one of the comma separated values exactly will be
   *   processed. Optional.
   *
   * @usage unbherbarium,pmportal drupal8
   *
   * @command github:repo:rebasedevprod
   */
  public function upmergeRepoDevToProd($match = '', $topics = '') {
    $match_array = explode(",", $match);
    $topics_array = explode(",", $topics);

    if (empty($match_array[0]) && empty($topics_array[0])) {
      $this->say(self::MESSAGE_REFUSING_REBASE_ALL_REPOSITORIES);
      return;
    }

    $this->rebaseDevToProd(
      $match_array,
      $topics_array
    );
  }

}
