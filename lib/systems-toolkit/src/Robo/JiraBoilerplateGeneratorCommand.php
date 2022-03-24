<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for JiraBoilerplateGeneratorCommand Robo commands.
 */
class JiraBoilerplateGeneratorCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * The source of the JIRA issue body.
   *
   * @var string
   */
  protected string $jiraInstanceSource;

  /**
   * The headers of the table being generated.
   *
   * @var array
   */
  protected array $jiraInstanceTableHeaders = ['ID', 'Instance'];

  /**
   * The default values for actions being defined.
   *
   * @var array
   */
  protected array $jiraInstanceDefaultValues = [];

  /**
   * The instance data (table rows).
   *
   * @var array
   */
  protected array $jiraInstanceTableRows = [];

  /**
   * Generates a JIRA issue description table for a multi-instance worklist.
   *
   * @throws \Exception
   *
   * @command jira:boilerplate:multi-instance-worklist
   */
  public function generateMultiInstanceWorklistTable() {
    $this->syskitIo->title('Generating multi-instance JIRA worklist boilerplate');
    $this->getWorklistTasks();
    $this->syskitIo->newLine();
    $this->getWorklistRepositories();
    $this->buildJiraTableSource();
    $this->syskitIo->newLine();
    $this->syskitIo->title('Worklist Source');
    $this->syskitIo->text($this->jiraInstanceSource);
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
    $this->jiraInstanceSource = '|| ' . implode(' || ', $this->jiraInstanceTableHeaders) . ' ||' . PHP_EOL;
    foreach ($this->githubRepositories as $idx => $repository) {
      $id = $idx + 1;
      $this->jiraInstanceSource .= "| $id | {$repository['name']} |";
      for ($i = 0; $i < count($this->jiraInstanceTableHeaders) - 2; $i++) {
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
  private function getFormattedWorkItemCellValue(string $cell_value = '') : string {
    if (empty($cell_value)) {
      return ' |';
    }
    else {
      return " $cell_value |";
    }
  }

}
