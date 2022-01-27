<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\DrupalModuleCommand;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\KubeExecTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for DrupalUpdatesCommand Robo commands.
 */
class DrupalUpdatesCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;
  use KubeExecTrait;

  /**
   * Should confirmations for this object be skipped?
   *
   * @var bool
   */
  private $noConfirm = FALSE;

  /**
   * Should only security updates be listed/applied?
   *
   * @var bool
   */
  private $securityOnly = FALSE;

  /**
   * A list of required updates in a tabular format.
   *
   * @var string[]
   */
  private $tabulatedUpdates = [];

  /**
   * A list of required updates .
   *
   * @var string[]
   */
  private $updates = [];

  /**
   * Rebuild all Drupal docker images and redeploy in their current state.
   *
   * @option array namespaces
   *   The namespaces to rebuild and deploy.
   *
   * @throws \Exception
   *
   * @command drupal:rebuild-redeploy
   */
  public function getRebuildDeployDrupalContainers($options = ['namespaces' => ['dev', 'prod']]) {
    $pod_selector = [
      'app=drupal',
    ];
    $this->setCurKubePodsFromSelector($pod_selector, $options['namespaces']);
    foreach ($this->kubeCurPods as $pod) {
      foreach ($options['namespaces'] as $namespace) {
        // Replace with gh-actions.
      }
    }
  }

  /**
   * Perform needed Drupal updates automatically.
   *
   * @option namespaces
   *   The extensions to match when finding files. Defaults to dev only.
   * @option array only-update
   *   A comma separated list of modules to query. Defaults to all.
   * @option array exclude
   *   A comma separated list of modules to exclude. Defaults to none.
   * @option bool security-only
   *   Only perform security updates.
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @throws \Exception
   *
   * @command drupal:doupdates
   */
  public function setDoDrupalUpdates($options = ['namespaces' => ['dev'], 'only-update' => [], 'exclude' => [], 'security-only' => FALSE, 'yes' => FALSE, 'multi-repo-delay' => '240']) {
    $this->getDrupalUpdates($options);
    $this->noConfirm = $options['yes'];

    if (!empty($this->updates)) {
      $this->say('Updates needed, querying corresponding repositories in GitHub');
      $continue = $this->setConfirmRepositoryList(
        array_keys($this->tabulatedUpdates),
        ['drupal8', 'drupal9'],
        [],
        [],
        'Upgrade Drupal Modules',
        $this->noConfirm
      );

      if ($continue) {
        $this->updateAllRepositories($options);
      }
    }
    else {
      $this->say('No updates needed to dev branches!');
    }
  }

  /**
   * Get the list of needed Drupal updates .
   *
   * @option array namespaces
   *   The extensions to match when finding files.
   * @option array only-update
   *   A comma separated list of modules to query. Defaults to all.
   * @option bool security-only
   *   Only retrieve security updates.
   *
   * @throws \Exception
   *
   * @command drupal:getupdates
   */
  public function getDrupalUpdates($options = ['namespaces' => ['dev', 'prod'], 'only-update' => [], 'exclude' => [], 'security-only' => FALSE]) {
    $this->securityOnly = $options['security-only'];

    $pod_selector = [
      'app=drupal',
    ];
    $this->setCurKubePodsFromSelector($pod_selector, $options['namespaces']);
    $this->setAllNeededUpdates($options['only-update'], $options['exclude']);
    $this->tabulateNeededUpdates($options['namespaces']);
    $this->printTabluatedUpdateTables();
  }

  /**
   * Set all needed updates for queued pods.
   */
  private function setAllNeededUpdates($module_whitelist = [], $module_exclude = []) {
    foreach ($this->kubeCurPods as $pod) {
      $this->setNeededUpdates($pod, $module_whitelist, $module_exclude);
    }
  }

  /**
   * Set needed updates for a specific pod.
   *
   * @param object $pod
   *   The kubernetes pod object obtained from JSON.
   */
  private function setNeededUpdates($pod, $module_whitelist = [], $module_exclude = []) {
    $args = [];
    $command = '/scripts/listDrupalUpdates.sh';
    if ($this->securityOnly) {
      $args[] = '-s';
    }

    $result = $this->kubeExecPod(
      $pod,
      $command,
      '-it',
      $args,
      FALSE
    );

    $updates_needed = $result->getMessage();
    $updates = json_decode($updates_needed);
    $this->filterIgnoredUpdates($updates);
    if (!empty($module_whitelist)) {
      $this->filterWhitelistUpdates($updates, $module_whitelist);
    }
    if (!empty($module_exclude)) {
      $this->filterExcludeUpdates($updates, $module_exclude);
    }
    if (!empty($updates)) {
      foreach ($updates as $up_idx => $cur_update) {
        if (empty($cur_update->recommended)) {
          unset($updates[$up_idx]);
        }
      }
      $this->updates[] = [
        'pod' => $pod,
        'updates' => $updates,
      ];
    }
  }

  /**
   * Set needed updates for a specific pod.
   *
   * @param string[] $branches
   *   The branches to set the updates for.
   */
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

  /**
   * Print tabulated updates to the console.
   */
  private function printTabluatedUpdateTables() {
    if (!empty($this->tabulatedUpdates)) {
      foreach ($this->tabulatedUpdates as $instance => $updates) {
        $this->printTabluatedInstanceUpdateTable($instance);
      }
    }
  }

  /**
   * Print the list of tabulated instance updates as a table.
   *
   * @param string $instance_name
   *   The name of the instance.
   */
  private function printTabluatedInstanceUpdateTable($instance_name) {
    if (!empty($this->tabulatedUpdates[$instance_name])) {
      $this->say("Updates available:");
      $table = new Table($this->output());
      $table->setHeaders([$instance_name]);
      $rows = [];
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

  /**
   * Get the formatted message referencing a particular update.
   *
   * @param object $update
   *   The update object that was generated from the JSON source.
   * @param bool $add_changelog
   *   Append the latest changelog in the form of a git extended commit message.
   *
   * @return string
   *   The formatted update message.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function getFormattedUpdateMessage($update, $add_changelog = FALSE) {
    $message = sprintf(
      '%s %s -> %s',
      $update->name,
      $update->existing_version,
      $update->recommended
    );

    if ($add_changelog) {
      $changelog = DrupalModuleCommand::moduleChangeLog($update->name, $update->recommended);
      if (!empty($changelog)) {
        $message = sprintf(
          "$message\n\n%s",
          $changelog
        );
      }
    }

    return $message;
  }

  /**
   * Determine if an update should be ignored.
   *
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return bool
   *   TRUE if the update should be ignored. FALSE otherwise.
   */
  private function isIgnoredUpdate($update) {
    $ignored_projects = [
      'field_collection'
    ];
    if (in_array($update->name, $ignored_projects)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determine if an update should be ignored due to version locks.
   *
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return bool
   *   TRUE if the update should be ignored. FALSE otherwise.
   */
  private function isLockedUpdate($update) {
    $locked_projects = [
      'bootstrap_barrio' => '5.1.6',
      'drupal' => '8.9.20',
      'search_api' => '8.x-1.23',
      'search_api_solr' => '4.2.1',
    ];
    if (array_key_exists($update->name, $locked_projects) && $locked_projects[$update->name] == $update->existing_version) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Filter any ignored updates.
   *
   * @param object[] $updates
   *   An array of update objects.
   */
  private function filterIgnoredUpdates(&$updates) {
    if (!empty($updates)) {
      foreach ($updates as $idx => $update) {
        if ($this->isIgnoredUpdate($update)) {
          $this->say("Ignoring (any) update for {$update->name}...");
          unset($updates[$idx]);
        }
        if ($this->isLockedUpdate($update)) {
          $this->say("Ignoring update for {$update->name} - locked at {$update->existing_version}...");
          unset($updates[$idx]);
        }
      }
    }
  }

  /**
   * Filter any non-whitelist updates.
   *
   * @param object[] $updates
   *   An array of update objects.
   */
  private function filterWhitelistUpdates(&$updates, $module_whitelist) {
    foreach ($updates as $idx => $update) {
      if (!in_array($update->name, $module_whitelist)) {
        $this->say("Ignoring update for {$update->name}...");
        unset($updates[$idx]);
      }
    }
  }

  /**
   * Filter any excluded updates.
   *
   * @param object[] $updates
   *   An array of update objects.
   */
  private function filterExcludeUpdates(&$updates, $module_exclude) {
    if (!empty($updates)) {
      foreach ($updates as $idx => $update) {
        if (in_array($update->name, $module_exclude)) {
          $this->say("Ignoring update for {$update->name} (excluded)...");
          unset($updates[$idx]);
        }
      }
    }
  }

  /**
   * Update all queued GitHub repositories.
   */
  private function updateAllRepositories($options) {
    foreach ($this->githubRepositories as $repository) {
      $updates_pushed = $this->updateRepository($repository);
      if ($updates_pushed) {
        $this->say("Sleeping for {$options['multi-repo-delay']} seconds to spread build times...");
        sleep($options['multi-repo-delay']);
      }
    }
  }

  /**
   * Update a specific GitHub repository with required updates.
   *
   * @param array $repository
   *   The associative array describing the repository.
   *
   * @return bool
   *   TRUE if an update was pushed to GitHub. FALSE otherwise.
   *
   * @throws \Github\Exception\ErrorException
   * @throws \Github\Exception\MissingArgumentException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function updateRepository(array $repository) {
    return $this->updateComposerJson($repository);
  }

  /**
   * Update and commit the build/composer.json file for a repository and branch.
   *
   * @param array $repository
   *   The associative array describing the repository.
   * @param string $branch
   *   The repository branch to perform the updates in.
   *
   * @return bool
   *   TRUE if an update was pushed to GitHub. FALSE otherwise.
   *
   * @throws \Github\Exception\ErrorException
   * @throws \Github\Exception\MissingArgumentException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function updateComposerJson($repository, $branch = 'dev') {
    $path = 'build/composer.json';
    $committer = ['name' => $this->userName, 'email' => $this->userEmail];
    $updates_pushed = FALSE;

    if (!empty($this->tabulatedUpdates[$repository['name']][$branch])) {
      $update_data = $this->tabulatedUpdates[$repository['name']][$branch];

      $repo_uri = "git@github.com:{$update_data['vcsOwner']}/{$update_data['vcsRepository']}.git";
      $this->say("Cloning $repo_uri...");
      $repo = GitRepo::setCreateFromClone($repo_uri);
      $repo->repo->checkout($branch);
      $repo_path = $repo->getTmpDir();
      $repo_build_file_path = "$repo_path/build/composer.json";
      $old_file_content = file_get_contents($repo_build_file_path);
      $composer_file = json_decode($old_file_content);

      if ($composer_file !== NULL) {
        $this->printTabluatedInstanceUpdateTable($repository['name']);
        if (!$this->noConfirm) {
          $do_all = $this->confirm('Perform all updates without interaction?');
        }
        else {
          $do_all = TRUE;
        }
        $updates_committed = FALSE;
        foreach ($update_data['updates'] as $cur_update) {
          if ($this->composerFileNeedsUpdate($composer_file, $cur_update)) {
            $commit_message = $this->getFormattedUpdateMessage($cur_update, TRUE);
            if ($do_all || $this->confirm("Apply [{$cur_update->name}] to $branch?")) {
              $this->say('Getting old file hash...');
              $old_file_hashes = $this->client->api('repo')
                ->contents()
                ->show($update_data['vcsOwner'], $update_data['vcsRepository'], $path, $branch);
              $this->say('Making changes...');
              $this->updateComposerFile(
                $composer_file,
                $cur_update
              );
              $new_content = json_encode($composer_file, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
              file_put_contents($repo_build_file_path, $new_content);
              $this->say($commit_message);
              file_put_contents('/tmp/syskit_commit_msg.txt', $commit_message);
              $repo->repo->addFile('build/composer.json');
              $repo->repo->execute(
                [
                  'commit',
                  '-F',
                  '/tmp/syskit_commit_msg.txt',
                ]
              );
              $updates_committed = TRUE;
            }
          }
          else {
            $this->say('Skipping already-applied update..');
          }
        }
        if ($updates_committed) {
          $repo->repo->push('origin', [$branch]);
          $updates_pushed = TRUE;
        }
      }
      else {
        $this->say('Failure to decode composer.json from GitHub!');
      }
    }

    return $updates_pushed;
  }

  /**
   * Determine if a composer.json file needs updates.
   *
   * @param object $composer_file
   *   The object obtained by parsing the composer file JSON.
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return bool
   *   TRUE if the composer file needs update, FALSE otherwise.
   */
  private function composerFileNeedsUpdate($composer_file, $update) {
    $project_name = $this->getFormattedProjectName($update);
    $old_version = $this->getFormattedProjectVersion($update->existing_version);
    if (!empty($composer_file->require->$project_name) && $composer_file->require->$project_name == $old_version) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the formatted version of an update's project name.
   *
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return string
   *   The formatted project name.
   */
  private function getFormattedProjectName($update) {
    if ($update->name == 'drupal') {
      return "drupal/core";
    }
    else {
      return "drupal/{$update->name}";
    }
  }

  /**
   * Get the formatted version of an update's project version.
   *
   * @param string $version_string
   *   The version string to parse for formatting.
   *
   * @return string
   *   The formatted project version.
   */
  private function getFormattedProjectVersion($version_string) {
    $formatted_version = str_replace('8.x-', NULL, $version_string);
    return $formatted_version;
  }

  /**
   * Update a composer file contents with a specific update.
   *
   * @param object $composer_file
   *   The object obtained by parsing the composer file JSON.
   * @param object $update
   *   The update object that was generated from the JSON source.
   */
  private function updateComposerFile(&$composer_file, $update) {
    $project_name = $this->getFormattedProjectName($update);
    $old_version = $this->getFormattedProjectVersion($update->existing_version);
    $new_version = $this->getFormattedProjectVersion($update->recommended);

    if (!empty($composer_file->require->$project_name) && $composer_file->require->$project_name == $old_version) {
      $composer_file->require->$project_name = $new_version;
    }
  }

}
