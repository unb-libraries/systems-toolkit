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
  private $noConfirm = FALSE;

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
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @throws \Exception
   *
   * @command drupal:8:doupdates
   */
  public function setDoDrupal8Updates($options = ['namespaces' => ['dev'], 'security-only' => FALSE, 'yes' => FALSE]) {
    $this->getDrupal8Updates($options);
    $this->noConfirm = $options['yes'];

    if (!empty($this->updates)) {
      $this->say('Updates needed, querying corresponding repositories in GitHub');
      $continue = $this->setConfirmRepositoryList(
        array_keys($this->tabulatedUpdates),
        ['drupal8'],
        [],
        [],
        'Upgrade Drupal Modules',
        $this->noConfirm
      );

      if ($continue) {
        $this->updateAllRepositories();
      }
    }
    else {
      $this->say('No updates needed to dev branches!');
    }
  }

  private function printTabluatedUpdateTables() {
    if (!empty($this->tabulatedUpdates)) {
      foreach($this->tabulatedUpdates as $instance => $updates) {
        $this->printTabluatedInstanceUpdateTable($instance);
      }
    }
  }

  private function printTabluatedInstanceUpdateTable($instance_name) {
    if (!empty($this->tabulatedUpdates[$instance_name])) {
      $this->say("Updates available:");
      $table = new Table($this->output());
      $table->setHeaders([$instance_name]);
      $rows=[];
      foreach ($this->tabulatedUpdates[$instance_name] as $environment => $data) {
        $rows[] = ["$environment:"];
        foreach ($data['updates'] as $module_update) {
          $rows[] = [$this->getFormattedUpdateMessage($module_update)];
        }
      }
      if (!empty($rows)) {
        $table->setRows($rows);
        $table->setStyle('borderless');
        $table->render();
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
          $this->tabulatedUpdates[$update['pod']->metadata->labels->instance][$update['pod']->metadata->namespace] = [
            'updates' => $update['updates'],
            'vcsOwner' => $update['pod']->metadata->labels->vcsOwner,
            'vcsRepository' => $update['pod']->metadata->labels->vcsRepository,
            'vcsRef' => $update['pod']->metadata->labels->vcsRef,
          ];
        }
      }
    }
  }

  private function updateComposerJson($repository, $branch = 'dev') {
    $path = 'build/composer.json';
    $committer = array('name' => 'Jacob Sanford', 'email' => 'jsanford@unb.ca');

    if (!empty($this->tabulatedUpdates[$repository['name']][$branch])) {
      $update_data = $this->tabulatedUpdates[$repository['name']][$branch];
      $old_file_content = $this->client->api('repo')->contents()->download($update_data['vcsOwner'], $update_data['vcsRepository'], $path, $branch);

      $composer_file = json_decode($old_file_content);
      if ($composer_file !== NULL) {
        $this->printTabluatedInstanceUpdateTable($repository['name']);
        if (!$this->noConfirm) {
          $do_all = $this->confirm('Perform all updates without interaction?');
        }
        else {
          $do_all = TRUE;
        }
        foreach ($update_data['updates'] as $cur_update) {
          $commit_message = $this->getFormattedUpdateMessage($cur_update);
          if ($do_all || $this->confirm("Apply [$commit_message] to $branch?")) {
            $this->say('Getting old file hash...');
            $old_file_hashes = $this->client->api('repo')->contents()->show($update_data['vcsOwner'], $update_data['vcsRepository'], $path, $branch);
            $this->say('Making changes...');
            $this->updateComposerFile(
              $composer_file,
              $cur_update
            );
            $new_content = json_encode($composer_file, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            $this->say($commit_message);
            $this->client->api('repo')->contents()->update($update_data['vcsOwner'], $update_data['vcsRepository'], $path, $new_content, $commit_message, $old_file_hashes['sha'], $branch, $committer);
            if ($do_all) {
              sleep(2);
            }
          }
        }
      }
      else {
        $this->say('Failure to decode composer.json from GitHub!');
      }
    }
  }

  private function updateComposerFile(&$composer_file, $update) {
    $project_name = $this->getFormattedProjectName($update);
    $old_version = $this->getFormattedProjectVersion($update->existing_version);
    $new_version = $this->getFormattedProjectVersion($update->recommended);

    if (!empty($composer_file->require->$project_name) && $composer_file->require->$project_name == $old_version) {
      $composer_file->require->$project_name = $new_version;
    }
  }

  private function getFormattedProjectName($update) {
    if($update->name == 'drupal') {
      return "drupal/core";
    }
    else {
      return "drupal/{$update->name}";
    }
  }

  private function getFormattedProjectVersion($version_string) {
    $formatted_version = str_replace('8.x-', NULL, $version_string);
    return $formatted_version;
  }
}
