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

  private $instances = [];
  private $securityOnly = FALSE;
  private $tabulatedUpdates = [];
  private $updates = [];

  /**
   * Get the list of needed Drupal 8 updates .
   *
   * @option array namespaces
   *   The extensions to match when finding files.
   * @option bool security-only
   *   Only retrieve security updates.
   *
   * @throws \Exception
   *
   * @command drupal:8:getupdates
   */
  public function getDrupal8Updates($options = ['namespaces' => ['dev', 'prod'], 'security-only' => FALSE]) {
    $this->securityOnly = $options['security-only'];
    $pod_selector = [
      'instance=cogswell.lib.unb.ca',
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
   * @option bool security-only
   *   Only perform security updates.
   *
   * @throws \Exception
   *
   * @command drupal:8:doupdates
   */
  public function setDoDrupal8Updates($options = ['namespaces' => ['dev'], 'security-only' => FALSE]) {
    $this->getDrupal8Updates($options);

    if (!empty($this->updates)) {
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
    else {
      $this->say('No updates needed to dev branches!');
    }
  }

  private function printTabluatedUpdateTables() {
    if (!empty($this->tabulatedUpdates)) {
      $this->say("Updates available:");
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
    $args = [];
    $command = '/scripts/listDrupalUpdates.sh';
    if ($this->securityOnly) {
      $args[] = '-s';
    }

    $result = $this->kubeExecPod(
      $pod->metadata->name,
      $pod->metadata->namespace,
      $command,
      '-it',
      $args,
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
