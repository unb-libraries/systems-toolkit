<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Symfony\ConsoleIO;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for GitHubRepoRebaseDevToProdCommand Robo commands.
 */
class GitHubRepoRebaseDevToProdCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  public const MESSAGE_CHECKING_OUT_REPO = 'Cloning repository to temporary folder...';
  public const MESSAGE_CONFIRM_PUSH = 'Was the rebase clean? Still want to push to GitHub?';
  public const MESSAGE_PUSH_RESULTS_TITLE = 'Push Results:';
  public const MESSAGE_REBASE_RESULTS_TITLE = 'Rebase Results:';
  public const MESSAGE_REBASING = 'Rebasing %s onto %s...';
  public const MESSAGE_REFUSING_REBASE_ALL_REPOSITORIES = 'Cowardly refusing to rebase all repositories on GitHub. Please include a $match argument or $topics. If you are having issues, try github:repo:rebasedevprod --help';
  public const OPERATION_TYPE = 'REBASE %s ONTO %s';
  public const UPMERGE_SOURCE_BRANCH = 'dev';
  public const UPMERGE_TARGET_BRANCH = 'prod';

  /**
   * Rebases dev onto prod for multiple GitHub Repositories.
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
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @throws \Exception
   *
   * @command github:repo:rebasedevprod
   * @usage unbherbarium,pmportal drupal8
   */
  public function upmergeRepoDevToProd(
    ConsoleIO $io,
    string $match = '',
    string $topics = '',
    array $options = [
      'multi-repo-delay' => '120',
      'yes' => FALSE,
    ]
  ) {
    $this->setIo($io);
    $match_array = explode(",", $match);
    $topics_array = explode(",", $topics);

    if (empty($match_array[0]) && empty($topics_array[0])) {
      $this->syskitIo->say(self::MESSAGE_REFUSING_REBASE_ALL_REPOSITORIES);
      return;
    }

    $this->rebaseDevToProd(
      $match_array,
      $topics_array,
      $options
    );
  }

  /**
   * Rebases dev onto prod for one or multiple GitHub repositories.
   *
   * @param array $match
   *   Only repositories whose names contain one of $match values will be
   *   processed. Optional.
   * @param array $topics
   *   Only repositories whose topics contain one of $topics values will be
   *   processed. Optional.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   * @option $repo-exclude
   *   A repository to exclude from the rebase. Defaults to none.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @throws \Exception
   */
  protected function rebaseDevToProd(
    array $match = [],
    array $topics = [],
    array $options = [
      'multi-repo-delay' => '120',
      'repo-exclude' => [],
      'yes' => FALSE,
    ]
  ) {
    // Get repositories.
    $continue = $this->setConfirmRepositoryList(
      $match,
      $topics,
      [],
      $options['repo-exclude'],
      sprintf(
        self::OPERATION_TYPE,
        self::UPMERGE_SOURCE_BRANCH,
        self::UPMERGE_TARGET_BRANCH
      ),
      $options['yes']
    );

    // Rebase and push up to GitHub.
    if ($continue) {
      foreach ($this->githubRepositories as $repository_data) {
        // Pull down.
        $this->syskitIo->title($repository_data['name']);
        $this->syskitIo->say(
          self::MESSAGE_CHECKING_OUT_REPO
        );
        $repo = GitRepo::setCreateFromClone($repository_data['ssh_url'], $this->tmpDir);
        if (!self::repoBranchesAreSynchronized($repo, self::UPMERGE_SOURCE_BRANCH, self::UPMERGE_TARGET_BRANCH)) {
          // Rebase.
          $repo->repo->checkout('prod');
          $this->syskitIo->say(
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
          $this->syskitIo->say(self::MESSAGE_REBASE_RESULTS_TITLE);
          $this->syskitIo->say(implode("\n", $rebase_output));

          // Push.
          if (!$options['yes']) {
            $continue = $this->syskitIo->confirm(self::MESSAGE_CONFIRM_PUSH);
          }
          else {
            $continue = TRUE;
          }
          if ($continue) {
            $push_output = $repo->repo->execute(
              [
                'push',
                'origin',
                self::UPMERGE_TARGET_BRANCH,
              ]
            );
            $this->syskitIo->say(self::MESSAGE_PUSH_RESULTS_TITLE);
            $this->syskitIo->say(implode("\n", $push_output));
          }
          $this->syskitIo->newLine();
          $this->syskitIo->say("Sleeping for {$options['multi-repo-delay']} seconds to spread build times...");
          sleep($options['multi-repo-delay']);
        }
        else {
          $this->syskitIo->say(
            sprintf(
              "Branches %s and %s are synchronized, skipping...",
              self::UPMERGE_SOURCE_BRANCH,
              self::UPMERGE_TARGET_BRANCH
            )
          );
          $this->syskitIo->newLine();
        }
      }
    }
  }

  /**
   * Determines if two branches of a repository are synchronized.
   *
   * @param \UnbLibraries\SystemsToolkit\Git\GitRepo $repo
   *   The repository to evaluate.
   * @param string $branch1
   *   The first branch.
   * @param string $branch2
   *   The second branch.
   *
   * @throws \CzProject\GitPhp\GitException
   *
   * @return bool
   *   TRUE if the branches are synchronized. False otherwise.
   */
  private static function repoBranchesAreSynchronized(GitRepo $repo, string $branch1, string $branch2) : bool {
    $repo->repo->checkout($branch1);
    $repo->repo->checkout($branch2);
    return self::getRepoHeadHash($repo, $branch1) ==
      self::getRepoHeadHash($repo, $branch2);
  }

  /**
   * Retrieves the repository's branch HEAD commit hash.
   *
   * @param \UnbLibraries\SystemsToolkit\Git\GitRepo $repo
   *   The repository to query.
   * @param string $branch
   *   The branch to query.
   *
   * @throws \CzProject\GitPhp\GitException
   *
   * @return string[]
   *   The value of the branch HEAD commit hash.
   */
  private static function getRepoHeadHash(GitRepo $repo, string $branch) : array {
    return $repo->repo->execute(
      [
        'log',
        $branch,
        '-1',
        '--format=%H',
      ]
    );
  }

}
