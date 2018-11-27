<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\JiraTrait;
use JiraRestApi\Project\ProjectService;
use JiraRestApi\JiraException;

/**
 * Class for BasicJiraCommand Robo commands.
 */
class BasicJiraCommand extends SystemsToolkitCommand {

  use JiraTrait;

  /**
   * Get a kubernetes service logs from the URI and namespace.
   *
   * @param string $project_id
   *   The project ID string, i.e. NBNP
   *
   * @throws \Exception
   *
   * @command jira:project:info
   */
  public function getProjectInfo($project_id) {
    try {
      $project = new ProjectService($this->jiraConfig);
      $project_info = $project->get($project_id);
      var_dump($project_info);
    } catch (JiraException $e) {
      print("Error Occured! " . $e->getMessage());
    }
  }

}
