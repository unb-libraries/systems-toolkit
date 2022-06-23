<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\KubeExecTrait;
use UnbLibraries\SystemsToolkit\Robo\DrupalModuleCommand;
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
  private bool $noConfirm = FALSE;

  /**
   * Should only security updates be listed/applied?
   *
   * @var bool
   */
  private bool $securityOnly = FALSE;

  /**
   * A list of required updates in a tabular format.
   *
   * @var string[]
   */
  private array $tabulatedUpdates = [];

  /**
   * A list of required updates .
   *
   * @var string[]
   */
  private array $updates = [];

  /**
   * Rebuilds all Drupal docker images and redeploy in their current state.
   *
   * @option $namespaces
   *   The namespaces to rebuild and deploy.
   *
   * @throws \Exception
   *
   * @command drupal:rebuild-redeploy
   */
  public function getRebuildDeployDrupalContainers(
    $options = [
      'namespaces' => [
        'dev',
        'prod',
      ],
    ]
  ) {
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
   * Performs needed Drupal updates automatically.
   *
   * @param array $options
   *   An array of CLI options to pass to the command.
   *
   * @option $namespaces
   *   The extensions to match when finding files. Defaults to dev only.
   * @option $only-update
   *   Restrict updating to a specific module. Defaults to all modules.
   * @option $exclude
   *   A comma separated list of modules to exclude. Defaults to none.
   * @option $security-only
   *   Only perform security updates.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \JsonException
   * @throws \Psr\Cache\InvalidArgumentException
   *
   * @command drupal:doupdates
   */
  public function setDoDrupalUpdates(
    array $options = [
      'namespaces' => ['dev'],
      'only-update' => [],
      'exclude' => [],
      'security-only' => FALSE,
      'yes' => FALSE,
      'multi-repo-delay' => self::DEFAULT_MULTI_REPO_DELAY,
    ]
  ) {
    $this->setCheckEmptyUpdateDef();
    $this->getDrupalUpdates($options);
    $this->noConfirm = $options['yes'];

    if (!empty($this->updates)) {
      $this->io()->title("Matching Pods to GitHub Repositories");
      $continue = $this->setConfirmRepositoryList(
        array_keys($this->tabulatedUpdates),
        ['drupal8', 'drupal9'],
        [],
        [],
        'Upgrade Drupal Modules',
        $this->noConfirm
      );

      if ($continue) {
        $this->io()->title("Applying Updates");
        $this->updateAllRepositories($options);
      }
    }
    else {
      $this->say('No updates needed to dev branches!');
    }
  }

  /**
   * Warns if the update exceptions and locked configurations are empty.
   */
  protected function setCheckEmptyUpdateDef() {
    $ignored_projects = Robo::Config()->get('syskit.drupal.updates.ignoredProjects') ?? [];
    $locked_projects = Robo::Config()->get('syskit.drupal.updates.lockedProjects') ?? [];
    if (empty($ignored_projects) && empty($locked_projects)) {
      if (!$this->confirm("No module ignores/locks have been defined in configuration (syskit.drupal.updates.ignoredProjects/syskit.drupal.updates.lockedProjects). This is atypical and likely will cause issues. Continue?")) {
        exit(0);
      }
    }
  }

  /**
   * Gets the list of needed Drupal updates.
   *
   * @option $namespaces
   *   The extensions to match when finding files.
   * @option $only-update
   *   Restrict needed updates to a specific module. Defaults to all modules.
   * @option $security-only
   *   Only retrieve security updates.
   *
   * @throws \Exception
   *
   * @command drupal:getupdates
   */
  public function getDrupalUpdates(
    $options = [
      'namespaces' => [
        'dev',
        'prod',
      ],
      'only-update' => [],
      'exclude' => [],
      'security-only' => FALSE,
    ]
  ) {
    $this->securityOnly = $options['security-only'];

    $pod_selector = [
      'app=drupal',
    ];
    $this->io()->title('Querying Drupal Pods For Needed Updates');
    $this->setCurKubePodsFromSelector($pod_selector, $options['namespaces']);
    $this->setAllNeededUpdates($options['only-update'], $options['exclude']);
    $this->tabulateNeededUpdates($options['namespaces']);
    $this->printTabulatedUpdateTables();
  }

  /**
   * Sets all needed updates for queued pods.
   *
   * @param string[] $module_whitelist
   *   An array of module names to restrict the updates to.
   * @param string[] $module_exclude
   *   An array of module names to exclude from the list.
   *
   * @throws \JsonException
   */
  private function setAllNeededUpdates(
    array $module_whitelist = [],
    array $module_exclude = [],
  ) {
    foreach ($this->kubeCurPods as $pod) {
      try {
        $this->setNeededUpdates($pod, $module_whitelist, $module_exclude);
      }
      catch (\Exception $e) {
        $this->io()->warning(
          sprintf(
            'Unable to query %s pod for updates',
            $pod->metadata->labels->instance
          )
        );
      }
    }
  }

  /**
   * Sets needed updates for a specific pod.
   *
   * @param object $pod
   *   The kubernetes pod object obtained from JSON.
   * @param string[] $module_whitelist
   *   An array of module names to restrict the updates to.
   * @param string[] $module_exclude
   *   An array of module names to exclude from the list.
   *
   * @throws \JsonException
   */
  private function setNeededUpdates($pod, array $module_whitelist = [], array $module_exclude = []) {
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
    $updates = json_decode(
      $updates_needed,
      NULL,
      512,
      JSON_THROW_ON_ERROR
    );
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
   * Sets needed updates for a specific pod.
   *
   * @param string[] $branches
   *   The branches to set the updates for.
   */
  private function tabulateNeededUpdates(array $branches = ['dev', 'prod']) {
    foreach ($this->updates as $update) {
      if (in_array($update['pod']->metadata->namespace, $branches)) {
        if (!empty($update['updates'])) {
          $this->tabulatedUpdates[$update['pod']->metadata->labels->instance][$update['pod']->metadata->namespace] = [
            'updates' => $update['updates'],
            'vcsOwner' => $update['pod']->metadata->labels->vcsOwner,
            'vcsRepository' => $update['pod']->metadata->labels->vcsRepository,
            'vcsRef' => $update['pod']->metadata->labels->vcsRef,
          ];
          ksort($this->tabulatedUpdates);
        }
      }
    }
  }

  /**
   * Prints tabulated updates to the console.
   */
  private function printTabulatedUpdateTables() {
    if (!empty($this->tabulatedUpdates)) {
      $this->io()->title("Updates available:");
      foreach ($this->tabulatedUpdates as $instance => $updates) {
        $this->printTabulatedInstanceUpdateTable($instance);
      }
    }
  }

  /**
   * Prints the list of tabulated instance updates as a table.
   *
   * @param string $instance_name
   *   The name of the instance.
   *
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function printTabulatedInstanceUpdateTable(string $instance_name) {
    if (!empty($this->tabulatedUpdates[$instance_name])) {
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
   * Gets the formatted message referencing a particular update.
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
  private function getFormattedUpdateMessage(object $update, bool $add_changelog = FALSE) : string {
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
   * Determines if an update should be ignored.
   *
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return bool
   *   TRUE if the update should be ignored. FALSE otherwise.
   */
  private function isIgnoredUpdate(object $update) : bool {
    $ignored_projects = Robo::Config()->get('syskit.drupal.updates.ignoredProjects') ?? [];
    if (in_array($update->name, $ignored_projects)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Determines if an update should be ignored due to version locks.
   *
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return bool
   *   TRUE if the update should be ignored. FALSE otherwise.
   */
  private function isLockedUpdate(object $update) : bool {
    $locked_projects = Robo::Config()->get('syskit.drupal.updates.lockedProjects') ?? [];
    if (array_key_exists($update->name, $locked_projects) && $locked_projects[$update->name] == $update->existing_version) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Filters any ignored updates.
   *
   * @param object[] $updates
   *   An array of update objects.
   */
  private function filterIgnoredUpdates(array &$updates) {
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
   * Filters any non-whitelist updates.
   *
   * @param object[] $updates
   *   An array of update objects.
   * @param string[] $module_whitelist
   *   An array of module names to restrict the updates to.
   */
  private function filterWhitelistUpdates(array &$updates, array $module_whitelist) {
    foreach ($updates as $idx => $update) {
      if (!in_array($update->name, $module_whitelist)) {
        $this->say("Ignoring update for {$update->name}...");
        unset($updates[$idx]);
      }
    }
  }

  /**
   * Filters any excluded updates.
   *
   * @param object[] $updates
   *   An array of update objects.
   * @param string[] $module_exclude
   *   An array of module names to exclude from the list.
   */
  private function filterExcludeUpdates(array &$updates, array $module_exclude) {
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
   * Updates all queued GitHub repositories.
   *
   * @param array $options
   *   An array of CLI options to pass to the command.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \JsonException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function updateAllRepositories(array $options) {
    $last_repo_key = array_key_last($this->githubRepositories);
    foreach ($this->githubRepositories as $repository_index => $repository) {
      $updates_pushed = $this->updateRepository($repository);
      if ($updates_pushed && $repository_index != $last_repo_key) {
        $this->say("Sleeping for {$options['multi-repo-delay']} seconds to spread build times...");
        sleep($options['multi-repo-delay']);
      }
    }
  }

  /**
   * Updates a specific GitHub repository with required updates.
   *
   * @param array $repository
   *   The associative array describing the repository.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \JsonException
   * @throws \Psr\Cache\InvalidArgumentException
   *
   * @return bool
   *   TRUE if an update was pushed to GitHub. FALSE otherwise.
   */
  private function updateRepository(array $repository) : bool {
    return $this->updateComposerJson($repository);
  }

  /**
   * Updates and commits the build/composer.json for a repository and branch.
   *
   * @param array $repository
   *   The associative array describing the repository.
   * @param string $branch
   *   The repository branch to perform the updates in.
   *
   * @return bool
   *   TRUE if an update was pushed to GitHub. FALSE otherwise.
   *
   * @throws \CzProject\GitPhp\GitException
   * @throws \JsonException
   * @throws \Psr\Cache\InvalidArgumentException
   */
  private function updateComposerJson(array $repository, string $branch = 'dev') : bool {
    $path = 'build/composer.json';
    $committer = ['name' => $this->userName, 'email' => $this->userEmail];
    $updates_pushed = FALSE;

    if (!empty($this->tabulatedUpdates[$repository['name']][$branch])) {
      $update_data = $this->tabulatedUpdates[$repository['name']][$branch];

      $repo_uri = "git@github.com:{$update_data['vcsOwner']}/{$update_data['vcsRepository']}.git";
      $this->say("Cloning $repo_uri...");
      $repo = GitRepo::setCreateFromClone($repo_uri, $this->tmpDir);
      $repo->repo->checkout($branch);
      $repo_path = $repo->getTmpDir();
      $this->say($repo_path);
      $repo_build_file_path = "$repo_path/build/composer.json";
      $old_file_content = file_get_contents($repo_build_file_path);
      $composer_file = json_decode(
        $old_file_content,
        NULL,
        512,
        JSON_THROW_ON_ERROR
      );

      if ($composer_file !== NULL) {
        $this->printTabulatedInstanceUpdateTable($repository['name']);
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

              $sort_command = "jq --indent 2 --sort-keys . $repo_build_file_path > $this->tmpDir/composer-syskit-sort.json && mv $this->tmpDir/composer-syskit-sort.json $repo_build_file_path";
              shell_exec($sort_command);

              $this->say($commit_message);
              $repo->repo->addFile('build/composer.json');
              $repo->repo->commit($commit_message);
              $updates_committed = TRUE;
            }
          }
          else {
            $this->say('Skipping already-applied update..');
          }
        }
        if ($updates_committed) {
          $repo->repo->push(['origin', $branch]);
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
   * Determines if a composer.json file needs updates.
   *
   * @param object $composer_file
   *   The object obtained by parsing the composer file JSON.
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return bool
   *   TRUE if the composer file needs update, FALSE otherwise.
   */
  private function composerFileNeedsUpdate(object $composer_file, object $update) : bool {
    $project_name = $this->getFormattedProjectName($update);
    $old_version = $this->getFormattedProjectVersion($update->existing_version);
    if (!empty($composer_file->require->$project_name) && $composer_file->require->$project_name == $old_version) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the formatted version of an update's project name.
   *
   * @param object $update
   *   The update object that was generated from the JSON source.
   *
   * @return string
   *   The formatted project name.
   */
  private function getFormattedProjectName(object $update) : string {
    if ($update->name == 'drupal') {
      return "drupal/core";
    }
    else {
      return "drupal/{$update->name}";
    }
  }

  /**
   * Gets the formatted version of an update's project version.
   *
   * @param string $version_string
   *   The version string to parse for formatting.
   *
   * @return string
   *   The formatted project version.
   */
  private function getFormattedProjectVersion(string $version_string) : string {
    return str_replace('8.x-', '', $version_string);
  }

  /**
   * Updates a composer file contents with a specific update.
   *
   * @param object $composer_file
   *   The object obtained by parsing the composer file JSON.
   * @param object $update
   *   The update object that was generated from the JSON source.
   */
  private function updateComposerFile(object &$composer_file, object $update) {
    $project_name = $this->getFormattedProjectName($update);
    $old_version = $this->getFormattedProjectVersion($update->existing_version);
    $new_version = $this->getFormattedProjectVersion($update->recommended);

    if (!empty($composer_file->require->$project_name) && $composer_file->require->$project_name == $old_version) {
      $composer_file->require->$project_name = $new_version;
    }
  }

}
