<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitGitHubRepoRebaseDevToProdCommand;

/**
 * Base class for SystemsToolkitGitHubDrupal8RebaseDevToProdCommand..
 */
class SystemsToolkitGitHubDrupal8RebaseDevToProdCommand extends SystemsToolkitGitHubRepoRebaseDevToProdCommand {

  /**
   * Rebase dev onto prod for one or multiple GitHub Drupal 8 Repositories.
   *
   * This command will rebase all commits that exist in the dev branch of a
   * GitHub Drupal 8 lean repository onto the prod branch.
   *
   * @param string $match
   *   A comma separated list of names to match. Only repositories whose names
   *   partially match at least one of the comma separated values will be
   *   processed. Optional.
   *
   * @usage unbherbarium,pmportal
   *
   * @command drupal:8:rebasedevprod
   */
  public function upmergeDrupalDevToProd($match = '') {
    $match_array = explode(",", $match);
    parent::rebaseDevToProd(
      $match_array,
      ['drupal8']
    );
  }

}
