<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use \UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use \UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for GitHubActionsRestartBuildsCommand Robo commands.
 */
class GitHubActionsRestartBuildsCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * Restart the latest builds for Github Actions deployed repositories.
   *
   * @param string $namespace
   *   The branch to operate on. Defaults to 'dev'.
   * @param string $match
   *   A comma separated list of strings to match. Only repositories whose names
   *   partially match at least one of the comma separated values will be
   *   processed. Optional.
   * @param string $tag
   *   The tag to match when selecting repositories. Defaults to all github
   *   actions tagged repositories.
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between restarting builds.
   *
   * @command github:actions:restart-latest
   * @usage github:actions:restart-latest 'dev' 'pmportal.org' 'drupal8' --yes
   */
  public function getGitHubActionsRestartLatestBuild($namespace = 'dev', $match = '', $tag = 'github-actions', $options = ['yes' => FALSE, 'multi-repo-delay' => '300']) {
    $matches = explode(',', $match);
    // Get repositories.
    $continue = $this->setConfirmRepositoryList(
      $matches,
      [$tag],
      [],
      [],
      'Restart Latest ',
      TRUE
    );

    if ($continue) {
      foreach ($this->githubRepositories as $repository_data) {
        $repo_owner=$repository_data['owner']['login'];
        $repo_name=$repository_data['name'];
        $workflows = $this->client->api('repo')->workflows()->all($repo_owner,$repo_name);
        if (!empty($workflows['workflows'])) {
          foreach ($workflows['workflows'] as $workflow) {
            if ($workflow['name'] == $repo_name) {
              $run = $this->client->api('repo')->workflowRuns()->listRuns($repo_owner, $repo_name, $workflow['id']);
              foreach ($run['workflow_runs'] as $cur_run) {
                if ($cur_run['head_branch'] == $namespace) {
                  $this->say("Restarting $repo_name Run #{$cur_run['id']}");
                  $this->client->api('repo')->workflowRuns()->rerun($repo_owner, $repo_name, $cur_run['id']);
                  break;
                }
              }
            }
          }
        }
        $this->say("Sleeping for {$options['multi-repo-delay']} seconds to spread build times...");
        sleep($options['multi-repo-delay']);
      }
    }
  }

}
