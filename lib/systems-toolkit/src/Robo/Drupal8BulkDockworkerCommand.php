<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\BulkDockworkerCommand;

/**
 * Class for Drupal8BulkDockworkerCommand Robo commands.
 */
class Drupal8BulkDockworkerCommand extends BulkDockworkerCommand {

  /**
   * Update the README file for all Drupal 8 instances.
   *
   * @param string $commit_message
   *   The commit message to use.
   *
   * @option namespaces
   *   The namespaces to apply the commit in. Defaults to dev.
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @command drupal:8:readme-bulk-update
   *
   * @usage drupal:8:readme-bulk-update 'IN-244 Update Readme Files' --yes
   *
   * @throws \Exception
   */
  public function setDoBulkDrupal8ReadmeUpdate($commit_message, $options = ['namespaces' => ['dev'], 'yes' => FALSE, 'multi-repo-delay' => '240']) {
    $this->setDoBulkDockworkerCommands(
      'dockworker:readme:update',
      '',
      'drupal8',
      $commit_message,
      $options
    );
  }

  /**
   * Update the github actions workflow file for all Drupal 8 instances.
   *
   * @param string $commit_message
   *   The commit message to use.
   *
   * @option namespaces
   *   The namespaces to apply the commit in. Defaults to dev.
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @command drupal:8:actions-bulk-update
   *
   * @usage drupal:8:actions-bulk-update 'IN-244 Update Readme Files' --yes
   *
   * @throws \Exception
   */
  public function setDoBulkDrupal8ActionsUpdate($commit_message, $options = ['namespaces' => ['dev'], 'yes' => FALSE, 'multi-repo-delay' => '240']) {
    $this->setDoBulkDockworkerCommands(
      'dockworker:gh-actions:update',
      '',
      'drupal8',
      $commit_message,
      $options
    );
  }

}
