<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Symfony\Component\Finder\Finder;
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
   * Deletes an entire year of a title's issues.
   *
   * @param string $title_id
   *   The parent digital title ID.
   * @param string $year
   *   The year to delete.
   * @param array $options
   *   An array of CLI options to pass to the command.
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @throws \Exception
   *
   * @usage "1 /mnt/issues/archive"
   *
   * @command newspapers.lib.unb.ca:delete-year
   */
  public function deleteTitleIssuesByYear(
    $title_id,
    $year,
    array $options = [
      'yes' => FALSE,
    ]
  ) {
    $this->options = $options;
    $issues = $this->getTitleYearIssues($title_id, $year);
    if (!empty($issues)) {
      print_r($issues);
      if ($this->options['yes'] == 'TRUE' || $this->confirm('OK to delete all the above issues?')) {
        foreach ($issues as $issue_id) {
          $this->setDeleteNewspapersIssue($issue_id);
          $this->say('Sleeping to inject sanity...');
          sleep(1);
        }
      }
    }
  }

  /**
   * Gets a list of a a newspaper title's issues.
   *
   * @param string $title_id
   *   The entity ID of the title to query.
   * @param string $year
   *   The year of the title to filter on.
   *
   * @return string[]
   *   A list of entity IDs matching the title and year.
   *
   * @throws \Exception
   */
  private function getTitleYearIssues($title_id, $year) {
    $ids = [];

    $ch = curl_init();
    $timeout = 5;

    $rest_uri = sprintf(
      "%s/serials-year-search/%s/%s",
      "https://" . self::NEWSPAPERS_FULL_URI,
      $title_id,
      $year
    );

    curl_setopt($ch,CURLOPT_URL, $rest_uri);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, $timeout);
    $data = curl_exec($ch);
    curl_close($ch);
    $raw_response = json_decode($data);
    if (!empty($raw_response->data)) {
      foreach ($raw_response->data as $entity_id) {
        $ids[] = $entity_id;
      }
    }
    return($ids);
  }

  /**
   * Deletes newspapers.lib.unb.ca imported markers from a tree.
   *
   * @param string $path
   *   The local path to delete the markers from.
   * @param array $options
   *   An array of CLI options to pass to the command.
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:delete:issue-markers
   */
  public function setDeleteNewspapersImportedMarkers(
    $path,
    array $options = [
      'yes' => FALSE,
    ]
  ) {
    $this->options = $options;
    $this->setDeleteFilesInTree($path, '.nbnp_processed');
    $this->setDeleteFilesInTree($path, '.nbnp_verified');
  }

  /**
   * Deletes files matching a name in a tree.
   *
   * @param string $path
   *   The local path to delete the files from.
   * @param string $file_name
   *   The filename to delete.
   */
  protected function setDeleteFilesInTree($path, $file_name) {
    $files_to_delete = [];
    $finder = new Finder();
    $finder->files()
      ->in($path)
      ->name($file_name)
      ->ignoreDotFiles(FALSE);

    if ($finder->hasResults()) {
      foreach ($finder as $file) {
        $files_to_delete[] = $file;
      }
    }

    if (!empty($files_to_delete)) {
      print_r($files_to_delete);
      if ($this->options['yes'] == 'TRUE' || $this->confirm('OK to delete all the above files?')) {
        foreach ($files_to_delete as $file_to_delete) {
          $this->say("Deleting $file_to_delete...");
          shell_exec("sudo rm -f $file_to_delete");
        }
      }
    }
  }

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
    if (empty($this->kubeCurPods)) {
      $this->setCurKubePodsFromSelector(['uri=' . self::NEWSPAPERS_FULL_URI], [self::NEWSPAPERS_NAMESPACE]);
    }

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
   * @param string[] $pod
   *   The pod ID to query.
   * @param string $issue_id
   *   The issue entity ID to delete.
   *
   * @return string[]
   *   The result of the command.
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
