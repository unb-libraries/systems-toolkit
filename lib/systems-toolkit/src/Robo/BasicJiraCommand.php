<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use JiraRestApi\JiraException;
use JiraRestApi\Project\ProjectService;
use UnbLibraries\SystemsToolkit\Robo\JiraTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for BasicJiraCommand Robo commands.
 */
class BasicJiraCommand extends SystemsToolkitCommand {

  use JiraTrait;

  /**
   * Get project info from the JIRA ID.
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
