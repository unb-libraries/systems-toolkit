<?php

namespace UnbLibraries\SystemsToolkit\Robo;

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
  protected $authKey;

  /**
   * The client to use.
   *
   * @var \Github\Client
   */
  protected $client;

  /**
   * The organizations to interact with.
   *
   * @var array
   */
  protected $organizations;

  /**
   * Get the github authKey from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setGitHubAuthKey() {
    $this->authKey = Robo::Config()->get('syskit.github.authKey');
    if (empty($this->authKey)) {
      throw new \Exception(sprintf('The GitHub authentication key has not been set in the configuration file. (authKey)'));
    }
  }

  /**
   * Get the github organization list from config.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setGitHubOrgs() {
    $this->organizations = Robo::Config()->get('syskit.github.organizations');
    if (empty($this->organizations)) {
      throw new \Exception(sprintf('No target organizations have been specified in the configuration file. (organizations)'));
    }
  }

  /**
   * Get the github authKey from config.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setGitHubClient() {
    try {
      $this->client = new Client();
      $this->client->authenticate($this->authKey, NULL, Client::AUTH_HTTP_TOKEN);
      $this->client->currentUser()->show();
    }
    catch (Exception $e) {
      throw new \Exception(sprintf('Authentication to GitHub failed. Is the authKey value set in the configuration file correct? This may also occur due to network outages.'));
    }
  }

  /**
   * Check if the user has access to a specific repository in GitHub.
   *
   * @param string $name
   *   The name of the repository to check.
   *
   * @return bool
   *   TRUE if the repository exists and the user has access. FALSE otherwise.
   */
  protected function getRepositoryExists($name) {
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
  protected function getGitHubRepositoryHasBranch($owner, $name, $branch) {
    foreach ($this->client->api('repo')->branches($owner, $name) as $cur_branch) {
      if ($cur_branch['name'] == $branch) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
