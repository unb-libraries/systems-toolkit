<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;
use Robo\Symfony\ConsoleIO;
use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\JiraTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;


/**
 * Class for MultipleProjectCreateTicketCommand Robo commands.
 */
class MultipleProjectCreateTicketCommand extends SystemsToolkitCommand {

  use JiraTrait;
  use GitHubMultipleInstanceTrait;

  public const EPIC_LINK_FIELD_ID = 'customfield_10002';
  public const DEFAULT_PROJECT_ID = '10600';
  public const DEFAULT_PROJECT_KEY = 'IN';

  /**
   * Creates a JIRA issue for multiple Github projects based on tags or name.
   *
   * @param string $match
   *   Only repositories whose names contain one of $match values will be
   *   processed.
   * @param string $topics
   *   Only repositories whose topics contain one of $topics values will be
   *   processed.
   * @param string $summary
   *   The issue summary.
   * @param string $description
   *   The issue description.
   * @param string $type
   *   The type of issue. Optional, defaults to 'task'.
   * @param string $epic
   *   The parent issue epic. Optional, defaults to none.
   * @param string $assignee
   *   The name of the assignee. Optional, defaults to the automatic asssignee.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   * @option $multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @throws \Exception
   *
   * @command jira:multiple-repo:create-issue
   * @usage jira:multiple-repo:create '' 'drupal8' 'Drupal 9.x Upgrade' 'Update Drupal to Drupal 9.x. See https://stackoverflow.com/c/unblibsystems/articles/131 .' 'Task' 'IN-243' --yes
   */
  public function createMultipleJiraTicket(
    ConsoleIO $io,
    string $match,
    string $topics,
    string $summary,
    string $description,
    string $type = 'Task',
    string $epic = '',
    string $assignee = '-1',
    array $options = [
      'yes' => FALSE,
      'multi-repo-delay' => '120',
    ]
  ) {
    $this->setIo($io);
    $continue = $this->setConfirmRepositoryList(
      [$match],
      [$topics],
      [],
      [],
      'Create Jira Tickets',
      $options['yes']
    );

    if ($continue) {
      $issues_to_create = [];
      $issuefields_to_create = [];
      foreach ($this->githubRepositories as $repository) {
        $verified_projects = [];
        if (!empty($slug = $this->getGitHubRepositoryJiraSlug($repository))) {
          try {
            $project = $this->jiraService->get($slug);
            $verified_projects[$project->id] = $project;
          }
          catch (JiraException $e) {
            print("Error Occured! " . $e->getMessage());
            $this->io()->newLine();
          }

          if (empty($verified_projects)) {
            // Repo with an incorrect Jira project. Add to the default.
            $verified_projects[self::DEFAULT_PROJECT_ID] = NULL;
          }

          foreach ($verified_projects as $project_id => $project_obj) {
            if (empty($project_obj->key)) {
              $project_slug = self::DEFAULT_PROJECT_KEY;
            }
            else {
              $project_slug = $project_obj->key;
            }

            if ($project_slug == self::DEFAULT_PROJECT_KEY) {
              $issue_summary = "{$repository['name']} : $summary";
            }
            else {
              $issue_summary = $issue_summary = $summary;
            }

            $issueField = new IssueField();
            $issueField->setProjectId($project_id)
              ->setAssigneeName($assignee)
              ->setSummary($issue_summary)
              ->setIssueType($type)
              ->setDescription($description);
            if (!empty($epic)) {
              $issueField->addCustomField(self::EPIC_LINK_FIELD_ID, $epic);
            }
            $issuefields_to_create[] = $issueField;
            $issues_to_create[] = [
              $repository['name'],
              $project_slug,
              $issue_summary,
              $description,
              $assignee,
              $type,
              $epic,
            ];
          }
        }
      }
      if (!empty($issues_to_create)) {
        if ($this->printConfirmIssuesToCreate($issues_to_create)) {
          foreach ($issuefields_to_create as $issuefield_key => $issuefield_to_create) {
            // $issueService = new IssueService($this->jiraConfig);
            $this->syskitIo->say("Creating issue for {$issues_to_create[$issuefield_key][0]}..");
            // $issueService->create($issuefield_to_create);
            $this->syskitIo->say("Sleeping to avoid overloading API...");
            sleep(5);
          }
        }
      }
    }
  }

  /**
   * @param $issues_to_create
   *
   * @return bool
   */
  protected function printConfirmIssuesToCreate($issues_to_create) {
    $table = new Table($this->io());
    $table
      ->setHeaders(
        [
          'Repository',
          'Project',
          'Summary',
          'Description',
          'Assignee',
          'Type',
          'Epic',
        ]
      )
      ->setRows($issues_to_create);
    $table->render();
    return $this->syskitIo->confirm('The following issues will be created. Continue?');
  }

}
