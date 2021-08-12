<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\BulkDockworkerCommand;

/**
 * Class for DrupalBulkDockworkerCommand Robo commands.
 */
class DrupalBulkDockworkerCommand extends BulkDockworkerCommand {

  /**
   * Update the README file for all Drupal instances.
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
   * @command drupal:readme-bulk-update
   *
   * @usage drupal:readme-bulk-update 'IN-244 Update Readme Files' --yes
   *
   * @throws \Exception
   */
  public function setDoBulkDrupalReadmeUpdate($commit_message, $options = ['namespaces' => ['dev'], 'yes' => FALSE, 'multi-repo-delay' => '240']) {
    $options['repo-name'] = [];
    $options['repo-tag'] = ['drupal8', 'drupal9'];
    $this->setDoBulkDockworkerCommands(
      'dockworker:readme:update',
      $commit_message,
      $options
    );
  }

  /**
   * Update the github actions workflow file for all Drupal instances.
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
   * @command drupal:actions-bulk-update
   *
   * @usage drupal:actions-bulk-update 'IN-244 Update Readme Files' --yes
   *
   * @throws \Exception
   */
  public function setDoBulkDrupalActionsUpdate($commit_message, $options = ['namespaces' => ['dev'], 'yes' => FALSE, 'multi-repo-delay' => '240']) {
    $options['repo-name'] = [];
    $options['repo-tag'] = ['drupal8', 'drupal9'];
    $this->setDoBulkDockworkerCommands(
      'dockworker:gh-actions:update',
      $commit_message,
      $options
    );
  }

}
