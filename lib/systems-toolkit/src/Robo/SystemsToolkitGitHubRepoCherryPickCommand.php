<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Git\GitFactory;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitGitHubMultipleInstanceCommand;
use Robo\Robo;

/**
 * Base class for SystemsToolkitGitHubRepoCherryPickCommand.
 */
class SystemsToolkitGitHubRepoCherryPickCommand extends SystemsToolkitGitHubMultipleInstanceCommand {

  const ERROR_MISSING_REPOSITORY = 'The repository %s was not found in any of your configured organizations.';
  const MESSAGE_REFUSING_CHERRY_ALL_REPOSITORIES = 'Cowardly refusing to cherry pick onto all repositories on GitHub. Please include a $match argument or $topics. If you are having issues, try github:repo:cherry-pick-multiple --help';
  const OPERATION_TYPE = 'cherry pick a commit from %s until other repositories';

  /**
   * Cherry pick a commit from a repo onto multiple others.
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
   */
  protected function cherryPickOneToMultiple($source_repository, array $target_topics = [], array $target_name_match = []) {

    // Verify repository exists.
    $this->getRepositoryExists($source_repository);

    // Get Commits and List
    // Ask User Which Commit to Rebase
    // Verify commit is in repo.

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

    // Get target branch.

    // Rebase and push up to GitHub.
    if ($continue) {
      foreach ($this->repositories as $repository_data) {
        // Pass.
      }
    }
  }

  /**
   * Cherry pick a commit from a repo onto multiple others.
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
   * @usage pmportal.org drupal8 unbherb
   *
   * @command github:repo:cherry-pick-multiple
   */
  public function cherryPickMultiple($source_repository, $target_topics = '', $target_name_match = '') {
    $match_array = explode(",", $target_name_match);
    $topics_array = explode(",", $target_topics);
    print_r($this->organizations);

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

}
