<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Symfony\ConsoleIO;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for GitHubActionsRestartBuildsCommand Robo commands.
 */
class GitHubActionsRestartBuildsCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * Restarts the latest builds for Github Actions deployed repositories.
   *
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option match
   *   Adds a string to match in the repository name when selecting
   *   repositories. Defaults to all names.
   * @option multi-repo-delay
   *   The amount of time to delay between restarting builds.
   * @option namespace
   *   Adds a namespace to restart the builds in. Defaults to 'dev'.
   * @option tag
   *   Adds a tag to match when selecting repositories. Defaults to all
   *   dockworker repositories.
   * @option yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @command github:actions:restart-latest
   * @usage github:actions:restart-latest --namespace=dev --namespace=prod --tag=drupal8 --match=pmportal.org --yes
   */
  public function getGitHubActionsRestartLatestBuild(
    ConsoleIO $io,
    array $options = [
      'match' => [],
      'multi-repo-delay' => '300',
      'namespace' => ['dev'],
      'tag' => ['dockworker'],
      'yes' => FALSE,
    ]
  ) {
    $this->setIo($io);
    $continue = $this->setConfirmRepositoryList(
      $options['match'],
      $options['tag'],
      [],
      [],
      'Restart Latest ',
      $options['yes']
    );

    if ($continue) {
      foreach ($this->githubRepositories as $repository_data) {
        $repo_owner = $repository_data['owner']['login'];
        $repo_name = $repository_data['name'];
        $workflows = $this->client->api('repo')->workflows()->all($repo_owner, $repo_name);
        if (!empty($workflows['workflows'])) {
          foreach ($workflows['workflows'] as $workflow) {
            if ($workflow['name'] == $repo_name) {
              $run = $this->client->api('repo')->workflowRuns()->listRuns($repo_owner, $repo_name, $workflow['id']);
              foreach ($options['namespace'] as $cur_namespace) {
                foreach ($run['workflow_runs'] as $cur_run) {
                  if ($cur_run['head_branch'] == $cur_namespace) {
                    $this->syskitIo->say("Restarting $repo_name/$cur_namespace Run #{$cur_run['id']}");
                    $this->client->api('repo')->workflowRuns()->rerun($repo_owner, $repo_name, $cur_run['id']);
                    break;
                  }
                }
              }
            }
          }
        }
        $this->syskitIo->say("Sleeping for {$options['multi-repo-delay']} seconds to spread build times...");
        sleep($options['multi-repo-delay']);
      }
    }
  }

}
