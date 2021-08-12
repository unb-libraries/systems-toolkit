<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use JiraRestApi\JiraException;
use JiraRestApi\Issue\IssueField;
use JiraRestApi\Issue\IssueService;
use UnbLibraries\SystemsToolkit\JiraTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;

/**
 * Class for MultipleProjectCreateTicketCommand Robo commands.
 */
class MultipleProjectCreateTicketCommand extends SystemsToolkitCommand {

  use JiraTrait;
  use GitHubMultipleInstanceTrait;

  const EPIC_LINK_FIELD_ID = 'customfield_10002';
  const DEFAULT_PROJECT_ID = '10600';
  const DEFAULT_PROJECT_KEY = 'IN';

  /**
   * Create a JIRA issue for multiple Github projects based on tags or name.
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
   *
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @throws \Exception
   *
   * @command jira:multiple-repo:create-issue
   * @usage jira:multiple-repo:create '' 'drupal8' 'Drupal 9.x Upgrade' 'Update Drupal to Drupal 9.x. See https://stackoverflow.com/c/unblibsystems/articles/131 .' 'Task' 'IN-243' --yes
   */
  public function createMultipleJiraTicket($match, $topics, $summary, $description, $type = 'Task', $epic = '', $options = ['yes' => FALSE, 'multi-repo-delay' => '120']) {
    $continue = $this->setConfirmRepositoryList(
      [$match],
      [$topics],
      [],
      [],
      'Create Jira Tickets',
      $options['yes']
    );

    if ($continue) {
      foreach ($this->githubRepositories as $repository) {
        $verified_projects = [];
        if (!empty($slug = $this->getGitHubRepositoryJiraSlug($repository))) {
          try {
            $project = $this->jiraService->get($slug);
            $verified_projects[$project->id] = $project;
          }
          catch (JiraException $e) {
            print("Error Occured! " . $e->getMessage());
          }
          if (empty($verified_projects)) {
            // Repo with an incorrect Jira project. Add to the default.
            $verified_projects[self::DEFAULT_PROJECT_ID] = self::DEFAULT_PROJECT_KEY;
            $issue_summary = "{$repository['name']} : $summary";
          }
          else {
            $issue_summary = $summary;
          }
          foreach ($verified_projects as $project_id => $project_key) {
            $issueField = new IssueField();
            $issueField->setProjectId($project_id)
              ->setSummary($issue_summary)
              ->setIssueType($type)
              ->setDescription($description);
            if (!empty($epic)) {
              $issueField->addCustomField(self::EPIC_LINK_FIELD_ID, $epic);
            }
            $issueService = new IssueService($this->jiraConfig);
            $this->say("Creating issue for {$repository['name']}..");
            $issueService->create($issueField);
            $this->say("Sleeping to avoid overloading API...");
            sleep(5);
          }
        }
      }
    }
  }

}
