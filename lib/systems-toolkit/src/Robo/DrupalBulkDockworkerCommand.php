<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\BulkDockworkerCommand;

/**
 * Class for DrupalBulkDockworkerCommand Robo commands.
 */
class DrupalBulkDockworkerCommand extends BulkDockworkerCommand {

  /**
   * Updates the README file for all Drupal instances.
   *
   * @param string $commit_message
   *   The commit message to use.
   * @param array $options
   *   An array of CLI options to pass to the command.
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
  public function setDoBulkDrupalReadmeUpdate(
    string $commit_message,
    array $options = [
      'namespaces' => ['dev'],
      'yes' => FALSE,
      'multi-repo-delay' => '240',
    ]
  ) {
    $options['repo-name'] = [];
    $options['repo-tag'] = ['drupal8', 'drupal9'];
    $this->setDoBulkDockworkerCommands(
      'readme:update',
      $commit_message,
      $options
    );
  }

  /**
   * Updates the GitHub Actions workflow file for all Drupal instances.
   *
   * @param string $commit_message
   *   The commit message to use.
   * @param array $options
   *   An array of CLI options to pass to the command.
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
  public function setDoBulkDrupalActionsUpdate(
    string $commit_message,
    array $options = [
      'namespaces' => ['dev'],
      'yes' => FALSE,
      'multi-repo-delay' => '240',
    ]
  ) {
    $options['repo-name'] = [];
    $options['repo-tag'] = ['drupal8', 'drupal9'];
    $this->setDoBulkDockworkerCommands(
      'ci:update-workflow-file',
      $commit_message,
      $options
    );
  }

}
