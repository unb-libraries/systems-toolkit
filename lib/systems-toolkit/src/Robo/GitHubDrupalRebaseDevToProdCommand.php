<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Symfony\ConsoleIO;
use UnbLibraries\SystemsToolkit\Robo\GitHubRepoRebaseDevToProdCommand;

/**
 * Class for GitHubDrupalRebaseDevToProdCommand Robo commands.
 */
class GitHubDrupalRebaseDevToProdCommand extends GitHubRepoRebaseDevToProdCommand {

  /**
   * Rebases dev onto prod for multiple Drupal Repositories. Robo Command.
   *
   * This command will rebase all commits that exist in the dev branch of a
   * GitHub Drupal lean repository onto the prod branch.
   *
   * @param string $match
   *   A comma separated list of names to match. Only repositories whose names
   *   partially match at least one of the comma separated values will be
   *   processed. Optional.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $repo-exclude
   *   A repository to exclude from the rebase. Defaults to none.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @throws \Exception
   *
   * @command drupal:rebasedevprod
   * @usage unbherbarium,pmportal
   */
  public function upmergeDrupalDevToProd(
    ConsoleIO $io,
    string $match = '',
    array $options = [
      'repo-exclude' => [],
      'yes' => FALSE,
      'multi-repo-delay' => '240',
    ]
  ) {
    $this->setIo($io);
    $match_array = explode(",", $match);
    parent::rebaseDevToProd(
      $match_array,
      ['drupal8', 'drupal9'],
      $options
    );
  }

}
