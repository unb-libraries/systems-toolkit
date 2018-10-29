<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\GitHubTrait;
use Symfony\Component\Console\Helper\Table;
use DateTime;

/**
 * Class for GetUserGithubActivityCommand Robo commands.
 */
class GetUserGithubActivityCommand extends SystemsToolkitCommand {

  use GitHubTrait;

  /**
   * Get a list of recent commits to GitHub by a user.
   *
   * @param string $userName
   *   The username to query.
   *
   * @usage JacobSanford
   *
   * @command github:user:activity
   */
  public function getActivity($userName) {
    $commits = [];

    $response = $this->client->getHttpClient()->get("/users/$userName/events");
    $activity = \Github\HttpClient\Message\ResponseMediator::getContent($response);
    foreach ($activity as $action) {
      if ($action['type'] = 'PushEvent') {
        $this->setCommitsFromAction($action, $commits);
      }
    }

    foreach ($commits as $push_date => $day_commits) {
      $this->say("Commits pushed on $push_date:");
      $this->setPrintDayCommits($day_commits);
    }
  }

  /**
   * Output the commits for a date.
   *
   * @param array $commits
   *   A list of commits from the GitHub API.
   * @param array $sha
   *   The SHA to check for.
   *
   * @return bool
   *   TRUE if the sha matches an existing commit. False otherwise.
   */
  private function getIsDuplicateCommit(array $commits, $sha) {
    foreach ($commits as $commit) {
      if ($commit[1] == $sha) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Set the commits from an action.
   *
   * @param array $action
   *   The GitHub api action array.
   * @param array $commits
   *   The list of commits to append found commits onto.
   */
  private function setCommitsFromAction($action, array &$commits) {
    if (!empty($action['payload']['commits'])) {
      $repo_name = $action['repo']['name'];
      $created_date = DateTime::createFromFormat("Y-m-d\TH:i:sP", $action['created_at']);
      $push_day = $created_date->format('Y-m-d');

      if (empty($commits[$push_day])) {
        $commits[$push_day] = [];
      }
      foreach ($action['payload']['commits'] as $commit) {
        if (!$this->getIsDuplicateCommit($commits[$push_day], $commit['sha'])) {
          $commits[$push_day][] = [
            $repo_name,
            $commit['sha'],
            $commit['message']
          ];
        }
      }
    }
  }

  /**
   * Output the commits for a date.
   *
   * @param array $day_commits
   *   The file to parse.
   */
  private function setPrintDayCommits(array $day_commits) {
    $table = new Table($this->output());
    $table->setHeaders(['Repository Name', 'Hash', 'Description']);
    $table->setRows($day_commits);
    $table->render();
  }

}
