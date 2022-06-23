<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use JiraRestApi\Field\Field;
use JiraRestApi\Field\FieldService;
use JiraRestApi\JiraException;
use JiraRestApi\Project\ProjectService;
use UnbLibraries\SystemsToolkit\JiraTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for BasicJiraCommand Robo commands.
 */
class BasicJiraCommand extends SystemsToolkitCommand {

  use JiraTrait;

  /**
   * Retrieves a project's critical information from the JIRA instance.
   *
   * @param string $project_id
   *   The project ID string, i.e. NBNP.
   *
   * @throws \Exception
   *
   * @command jira:project:info
   * @usage NBNP
   */
  public function getProjectInfo(
    string $project_id
  ) : void {
    try {
      $project = new ProjectService($this->jiraConfig);
      $project_info = $project->get($project_id);
      var_dump($project_info);
      $fieldService = new FieldService($this->jiraConfig);

      // Return custom field only.
      $ret = $fieldService->getAllFields(Field::CUSTOM);

      var_dump($ret);
    }
    catch (JiraException $e) {
      print("Error Occurred! " . $e->getMessage());
    }
  }

}
