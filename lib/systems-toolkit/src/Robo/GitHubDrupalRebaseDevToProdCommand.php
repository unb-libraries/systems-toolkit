<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\GitHubRepoRebaseDevToProdCommand;

/**
 * Class for GitHubDrupalRebaseDevToProdCommand Robo commands.
 */
class GitHubDrupalRebaseDevToProdCommand extends GitHubRepoRebaseDevToProdCommand {

  /**
   * Rebase dev onto prod for multiple Drupal Repositories. Robo Command.
   *
   * This command will rebase all commits that exist in the dev branch of a
   * GitHub Drupal lean repository onto the prod branch.
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
   * @command drupal:rebasedevprod
   */
  public function upmergeDrupalDevToProd($match = '', $options = ['yes' => FALSE, 'multi-repo-delay' => '240']) {
    $match_array = explode(",", $match);
    parent::rebaseDevToProd(
      $match_array,
      ['drupal8', 'drupal9'],
      $options
    );
  }

}