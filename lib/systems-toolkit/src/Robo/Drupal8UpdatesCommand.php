<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\KubeExecTrait;
use Symfony\Component\Console\Helper\Table;

/**
 * Class for Drupal8UpdatesCommand Robo commands.
 */
class Drupal8UpdatesCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;
  use KubeExecTrait;

  private $updates = [];
  private $tabulatedUpdates = [];
  private $instances = [];

  /**
   * Get the list of needed Drupal 8 updates .
   *
   * @option namespaces
   *   The extensions to match when finding files.
   *
   * @throws \Exception
   *
   * @command drupal:8:getupdates
   */
  public function getDrupal8Updates($options = ['namespaces' => ['dev', 'prod']]) {
    $pod_selector = [
      'app=drupal',
      'appMajor=8',
    ];
    $this->setCurKubePodsFromSelector($pod_selector, $options['namespaces']);
    $this->setAllNeededUpdates();
    $this->tabulateNeededUpdates($options['namespaces']);
    $this->printTabluatedUpdateTables();
  }

  /**
   * Perform needed Drupal 8 updates automatically.
   *
   * @option namespaces
   *   The extensions to match when finding files. Defaults to dev only.
   *
   * @throws \Exception
   *
   * @command drupal:8:doupdates
   */
  public function setDoDrupal8Updates($options = ['namespaces' => ['dev']]) {
    $this->getDrupal8Updates($options);

    $continue = $this->setConfirmRepositoryList(
      array_keys($this->tabulatedUpdates),
      ['drupal8'],
      [],
      [],
      'Upgrade Drupal Modules'
    );

    if ($continue) {

    }
  }

  private function printTabluatedUpdateTables() {
    $this->say("Updates available:");
    if (!empty($this->tabulatedUpdates)) {
      foreach($this->tabulatedUpdates as $instance => $updates) {
        $table = new Table($this->output());
        $table->setHeaders([$instance]);
        $rows=[];
        foreach ($updates as $environment => $module_updates) {
          $row_contents = "$environment:\n";
          foreach ($module_updates as $module_update) {
            $row_contents .= $this->getFormattedUpdateMessage($module_update);
          }
          $rows[] = [$row_contents];
        }
        if (!empty($rows)) {
          $table->setRows($rows);
          $table->setStyle('borderless');
          $table->render();
        }
      }
    }
  }

  private function getFormattedUpdateMessage($update) {
    return sprintf(
      '%s %s->%s (%s)',
      $update->name,
      $update->existing_version,
      $update->recommended,
      $update->status
    );
  }

  private function updateAllRepositories() {
    foreach($this->githubRepositories as $repository) {
      $this->updateRepository($repository);
    }
  }

  private function updateRepository($repository) {
    $this->updateComposerJson($repository);
  }

  private function setAllNeededUpdates() {
    foreach($this->kubeCurPods as $pod) {
      $this->setNeededUpdates($pod);
    }
  }

  private function setNeededUpdates($pod) {
    $command = '/scripts/listDrupalUpdates.sh';
    $result = $this->kubeExecPod(
      $pod->metadata->name,
      $pod->metadata->namespace,
      $command,
      '-it',
      [],
      FALSE
    );

    $updates_needed = $result->getMessage();
    if (!empty($updates_needed)) {
      $this->updates[] = [
        'pod' => $pod,
        'updates' => json_decode($updates_needed),
      ];
    }
  }

  private function tabulateNeededUpdates($branches = ['dev', 'prod']) {
    foreach ($this->updates as $update) {
      if (in_array($update['pod']->metadata->namespace, $branches)) {
        if (!empty($update['updates'])) {
          $this->tabulatedUpdates[$update['pod']->metadata->labels->instance][$update['pod']->metadata->namespace] = $update['updates'];
        }
      }
    }
  }

  private function updateComposerJson($repository) {
    $path = 'build/composer.json';
    $committer = array('name' => 'Jacob Sanford', 'email' => 'jsanford@unb.ca');

    // $oldFile = $this->gitHubClient->api('repo')->contents()->show('KnpLabs', 'php-github-api', $path, $branch);
    // $fileInfo = $client->api('repo')->contents()->update('KnpLabs', 'php-github-api', $path, $content, $commitMessage, $oldFile['sha'], $branch, $committer);
  }

}
