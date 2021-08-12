<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\KubeExecTrait;
use UnbLibraries\SystemsToolkit\Robo\BasicKubeCommand;

/**
 * Class for NewspapersLibUnbCaDeleteCommand Robo commands.
 */
class NewspapersLibUnbCaDeleteCommand extends BasicKubeCommand {

  use KubeExecTrait;

  const NEWSPAPERS_FULL_URI = 'newspapers.lib.unb.ca';
  const NEWSPAPERS_NAMESPACE = 'prod';

  /**
   * Delete a newspapers.lib.unb.ca digital issue and associated assets.
   *
   * @param string $issue_id
   *   The issue entity ID.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:delete:issue
   */
  public function setDeleteNewspapersIssue($issue_id) {
    $this->setCurKubePodsFromSelector(['uri=' . self::NEWSPAPERS_FULL_URI], [self::NEWSPAPERS_NAMESPACE]);
    foreach ($this->kubeCurPods as $pod) {
      $this->say(
        sprintf(
          'Deleting Issue #%s from %s',
          $issue_id,
          $pod->metadata->name
        )
      );
      $this->setDrushDeleteIssueCommand($pod, $issue_id);
    }
  }

  /**
   * Delete an issue entity via drush.
   *
   * @param $pod
   *   The pod ID to query.
   * @param $issue_id
   *   The issue entity ID to delete.
   *
   * @return string[]
   */
  private function setDrushDeleteIssueCommand($pod, $issue_id) {
    $delete_command = sprintf(
     '$storage = \Drupal::entityTypeManager()->getStorage("digital_serial_issue"); $page = $storage->load(%s); $page->delete();',
      $issue_id
    );
    $command_string = trim(
      sprintf(
        "%s '--kubeconfig=%s' '--namespace=%s' exec %s -- drush eval '%s'",
        $this->kubeBin,
        $this->kubeConfig,
        $pod->metadata->namespace,
        $pod->metadata->name,
        $delete_command
      )
    );
    exec($command_string, $output, $return);
    return($output);
  }

}
