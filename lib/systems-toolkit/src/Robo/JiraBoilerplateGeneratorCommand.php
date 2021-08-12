<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;

/**
 * Class for JiraBoilerplateGeneratorCommand Robo commands.
 */
class JiraBoilerplateGeneratorCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * The JIRA actions to perform.
   */
  protected $jiraInstanceSource = NULL;

  /**
   * The actions to perform.
   */
  protected $jiraInstanceTableHeaders = ['ID', 'Instance'];

  /**
   * The default values for table headers.
   */
  protected $jiraInstanceDefaultValues = [];

  /**
   * The actions to perform.
   */
  protected $jiraInstanceTableRows = [];

  /**
   * Generates a JIRA issue description table for a multi-instance worklist.
   *
   * @throws \Exception
   *
   * @command jira:boilerplate:multi-instance-worklist
   */
  public function generateMultiInstanceWorklistTable() {
    $this->io()->title('Generating multi-instance JIRA worklist boilerplate');
    $this->getWorklistTasks();
    $this->io()->newLine();
    $this->getWorklistRepositories();
    $this->buildJiraTableSource();
    $this->io()->newLine();
    $this->io()->title('Worklist Source');
    $this->io()->text($this->jiraInstanceSource);
  }

  /**
   * Gets a list of tasks for the multi-instance worklist.
   */
  private function getWorklistTasks() {
    $need_action = TRUE;
    $action_no = 1;

    while ($need_action == TRUE) {
      $action = $this->ask("Enter a short description of task #$action_no (16 Char Max, Enter to Stop)");
      if (!empty($action)) {
        $this->jiraInstanceTableHeaders[] = $action;
        $this->jiraInstanceDefaultValues[] = $this->ask("Enter a default value for task #$action_no (Enter for None)");
        $action_no++;
      }
      else {
        if ($action_no > 1) {
          $need_action = FALSE;
        }
        else {
          $this->say('You must specify at least one action!');
        }
      }
    }
  }

  /**
   * Gets a list of repositories to add to the multi-instance worklist.
   */
  private function getWorklistRepositories() {
    $topic = $this->askDefault('GitHub topic to include?', 'drupal8');
    $this->setRepositoryList(
      [],
      [$topic],
      [],
      []
    );
  }

  /**
   * Build the multi-instance worklist table JIRA source.
   */
  private function buildJiraTableSource() {
    $this->jiraInstanceSource = NULL;
    $this->jiraInstanceSource = '|| '. implode(' || ', $this->jiraInstanceTableHeaders) . ' ||' . PHP_EOL;
    foreach ($this->githubRepositories as $idx => $repository) {
      $id = $idx + 1;
      $this->jiraInstanceSource .= "| $id | {$repository['name']} |";
      for($i=0; $i < count($this->jiraInstanceTableHeaders) - 2; $i++) {
        $this->jiraInstanceSource .= $this->getFormattedWorkItemCellValue($this->jiraInstanceDefaultValues[$i]);
      }
      $this->jiraInstanceSource .= PHP_EOL;
    }
  }

  /**
   * Gets a formatted cell value for a work item.
   *
   * @param string $cell_value
   *   The value of the cell.
   *
   * @return string
   *   The formatted cell value.
   */
  private function getFormattedWorkItemCellValue($cell_value = NULL) {
    if (empty($cell_value)) {
      return ' |';
    }
    else {
      return " $cell_value |";
    }
  }

}
