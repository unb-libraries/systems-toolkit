<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\GitHubRepoRebaseDevToProdCommand;

/**
 * Class for GitHubDrupal8RebaseDevToProdCommand Robo commands.
 */
class GitHubDrupal8RebaseDevToProdCommand extends GitHubRepoRebaseDevToProdCommand {

  /**
   * Rebase dev onto prod for multiple Drupal 8 Repositories. Robo Command.
   *
   * This command will rebase all commits that exist in the dev branch of a
   * GitHub Drupal 8 lean repository onto the prod branch.
   *
   * @param string $match
   *   A comma separated list of names to match. Only repositories whose names
   *   partially match at least one of the comma separated values will be
   *   processed. Optional.
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @throws \Exception
   *
   * @usage unbherbarium,pmportal
   *
   * @command drupal:8:rebasedevprod
   */
  public function upmergeDrupalDevToProd($match = '', $options = ['yes' => FALSE, 'multi-repo-delay' => '240']) {
    $match_array = explode(",", $match);
    parent::rebaseDevToProd(
      $match_array,
      ['drupal8'],
      $options
    );
  }

}
