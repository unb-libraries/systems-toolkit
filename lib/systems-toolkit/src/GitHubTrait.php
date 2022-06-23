<?php

namespace UnbLibraries\SystemsToolkit;

use Github\AuthMethod;
use Github\Client;
use Robo\Robo;

/**
 * Trait for interacting with GitHub.
 */
trait GitHubTrait {

  /**
   * The auth key to access the GitHub API.
   *
   * @var string
   */
  protected string $authKey;

  /**
   * The client to use.
   *
   * @var \Github\Client
   */
  protected Client $client;

  /**
   * The commit username.
   *
   * @var string
   */
  protected string $userName;

  /**
   * The commit user email.
   *
   * @var string
   */
  protected string $userEmail;

  /**
   * The organizations to interact with.
   *
   * @var array
   */
  protected array $organizations = [];

  /**
   * Get the GitHub authKey from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setGitHubAuthKey() : void {
    $this->authKey = Robo::Config()->get('syskit.github.authKey');
    if (empty($this->authKey)) {
      throw new \Exception(sprintf('The GitHub authentication key has not been set in the configuration file. (authKey)'));
    }
  }

  /**
   * Get the GitHub user email from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setGitHubEmail() : void {
    $this->userEmail = Robo::Config()->get('syskit.github.userEmail');
    if (empty($this->userEmail)) {
      throw new \Exception(sprintf('The GitHub user name has not been set in the configuration file. (userEmail)'));
    }
  }

  /**
   * Get the GitHub organization list from config.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setGitHubOrgs() : void {
    $this->organizations = Robo::Config()->get('syskit.github.organizations');
    if (empty($this->organizations)) {
      throw new \Exception(sprintf('No target organizations have been specified in the configuration file. (organizations)'));
    }
  }

  /**
   * Get the GitHub authKey from config.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setGitHubClient() : void {
    try {
      $this->client = new Client();
      $this->client->authenticate(
        $this->authKey,
        NULL,
        AuthMethod::ACCESS_TOKEN
      );
      $this->client->currentUser()->show();
    }
    catch (\Exception $e) {
      throw new \Exception(
        sprintf(
          'Authentication to GitHub failed [%s]. Is the authKey value set in the configuration file correct? This may also occur due to network outages.',
          $e->getMessage()
        )
      );
    }
  }

  /**
   * Get the GitHub username from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setGitHubUserName() : void {
    $this->userName = Robo::Config()->get('syskit.github.userName');
    if (empty($this->userName)) {
      throw new \Exception(sprintf('The GitHub user name has not been set in the configuration file. (userName)'));
    }
  }

  /**
   * Check if the user has access to a specific repository in GitHub.
   *
   * @param string $name
   *   The name of the repository to check.
   *
   * @return mixed
   *   The details of the repo if exists and user has access. FALSE otherwise.
   */
  protected function getRepositoryExists(string $name) : mixed {
    foreach ($this->organizations as $organization) {
      $repo_details = $this->client->api('repo')->show($organization, $name);
      if (!empty($repo_details['ssh_url'])) {
        return $repo_details;
      }
    }
    return FALSE;
  }

  /**
   * Check if a GitHub repository has a specific branch.
   *
   * @param string $owner
   *   The owner of the repository.
   * @param string $name
   *   The name of the repository to check.
   * @param string $branch
   *   The branch name to check for.
   *
   * @return bool
   *   TRUE if the repository has the branch. FALSE otherwise.
   */
  protected function getGitHubRepositoryHasBranch(
    string $owner,
    string $name,
    string $branch
  ) : bool {
    foreach ($this->client->api('repo')->branches($owner, $name) as $cur_branch) {
      if ($cur_branch['name'] == $branch) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Gets a GitHub repository's dockworker defined JIRA project slug.
   *
   * @param array $repository
   *   The owner of the repository.
   *
   * @return string
   *   The value of the project slug, if set. Returns an empty string if not.
   *
   * @throws \Github\Exception\ErrorException
   */
  protected function getGitHubRepositoryJiraSlug(array $repository) : string {
    $dockworker_yml_path = '.dockworker/dockworker.yml';
    $dockworker_file_content = $this->client->api('repo')->contents()->download($repository['owner']['login'], $repository['name'], $dockworker_yml_path, $repository['default_branch']);
    $dockworker_yml = yaml_parse($dockworker_file_content);
    if (!empty($dockworker_yml['dockworker']['application']['project_prefix'])) {
      if (is_array($dockworker_yml['dockworker']['application']['project_prefix'])) {
        return reset($dockworker_yml['dockworker']['application']['project_prefix']);
      }
      return $dockworker_yml['dockworker']['application']['project_prefix'];
    }
    return '';
  }

}
