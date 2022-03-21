<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use \UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use \UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\TableCell;

/**
 * Class for GitHubActionsRepoBuildReportCommand Robo commands.
 */
class GitHubActionsRepoBuildReportCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * Show the latest build results for GitHub Actions build repositories.
   *
   * This command will list the latest github action runs in each branch of
   * the GitHub repository.
   *
   * @param string $match
   *   A comma separated list of strings to match. Only repositories whose names
   *   partially match at least one of the comma separated values will be
   *   processed. Optional.
   *
   * @option $only-failure
   *   Show only failed runs.
   *
   * @throws \Exception
   *
   * @usage unbherbarium,pmportal
   *
   * @command github:actions:status
   */
  public function getGitHubActionsBuildReports($match = '', $options = ['only-failure' => FALSE]) {
    $matches = explode(',', $match);
    // Get repositories.
    $continue = $this->setConfirmRepositoryList(
      $matches,
      ['github-actions'],
      [],
      [],
      'List Build Status',
      TRUE
    );

    if ($continue) {
      $workflow_data = [];
      foreach ($this->githubRepositories as $repository_data) {
        $repo_owner = $repository_data['owner']['login'];
        $repo_name = $repository_data['name'];
        $workflows = $this->client->api('repo')->workflows()->all($repo_owner,$repo_name);
        if (!empty($workflows['workflows'])) {
          foreach ($workflows['workflows'] as $workflow) {
            if ($workflow['name'] == $repo_name) {
              $workflow_data[] = [
                'repository' => $repository_data,
                'workflow' => $workflow,
                'runs' => $this->client->api('repo')->workflowRuns()->listRuns($repo_owner, $repo_name, $workflow['id'])
              ];
            }
          }
        }
      }

      // Tabulate Data
      if (!empty($workflow_data)) {
        $table_rows = [];
        foreach ($workflow_data as $repository_data) {
          if (!empty($repository_data['runs'])) {
            $first_row_of_repo = TRUE;
            $branches_found = [];
            foreach ($repository_data['runs']['workflow_runs'] as $run) {
              $job_errors = [];
              if (!in_array($run['head_branch'], $branches_found)) {
                if (!$options['only-failure'] || $run['conclusion'] == 'failure') {
                  if ($run['conclusion'] == 'success') {
                    $format_wrapper = 'info';
                  }
                  elseif (empty($run['conclusion'])) {
                    $run['conclusion'] = 'building';
                    $format_wrapper = 'comment';
                  }
                  else {
                    $format_wrapper = 'error';
                  }
                  $table_rows[] = [
                    $first_row_of_repo ? $run['name'] : NULL,
                    "<$format_wrapper>" . $run['head_branch'] . "</$format_wrapper>",
                    "<$format_wrapper>" . $run['run_number'] . "</$format_wrapper>",
                    "<$format_wrapper>" . $run['conclusion'] . "</$format_wrapper>",
                    "<$format_wrapper>" . $run['html_url'] . "</$format_wrapper>",
                  ];
                  $branches_found[] = $run['head_branch'];
                  $first_row_of_repo = FALSE;
                }
                $branches_found[] = $run['head_branch'];
              }
            }
          }
        }

        if (!empty($table_rows)) {
          $table = new Table($this->output());
          $table
            ->setHeaders(['Repository', 'Branch', 'ID', 'Status', 'URL'])
            ->setRows($table_rows)
          ;
          $table->render();
        }
      }
    }



  }

}
