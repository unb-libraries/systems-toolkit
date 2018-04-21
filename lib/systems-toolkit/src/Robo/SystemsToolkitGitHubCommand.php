<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitGitCommand;
use Github\Client;

/**
 * Base class for SystemsToolkitGitHubCommand Robo commands.
 */
class SystemsToolkitGitHubCommand extends SystemsToolkitGitCommand {

  const ERROR_AUTH_KEY_UNSET = 'The GitHub authentication key has not been set in %s. (authKey)';
  const ERROR_CANNOT_AUTHENTICATE = 'Authentication to GitHub failed. Is the authKey value set in %s correct? This may also occur due to network outages.';
  const ERROR_ORGS_UNSET = 'No target organizations have been specified in %s. (organizations)';

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
   * @hook init
   */
  public function setGitHubAuthKey() {
    $this->authKey = Robo::Config()->get('syskit.github.authKey');
    if (empty($this->authKey)) {
      throw new \Exception(sprintf(self::ERROR_AUTH_KEY_UNSET, $this->configFile));
    }
  }

  /**
   * Get the github organization list from config.
   *
   * @hook pre-init
   */
  public function setGitHubOrgs() {
    $this->organizations = Robo::Config()->get('syskit.github.organizations');
    if (empty($this->organizations)) {
      throw new \Exception(sprintf(self::ERROR_ORGS_UNSET, $this->configFile));
    }
  }

  /**
   * Get the github authKey from config.
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
      throw new \Exception(sprintf(self::ERROR_CANNOT_AUTHENTICATE, $this->configFile));
    }
  }

  /**
   * Check if the user has access to a specific repository in GitHub.
   */
  protected function getRepositoryExists($name) {
    foreach ($this->organizations as $organization) {
      $repo_details = $this->client->api('repo')->show($organization, $name);
      if (!empty($repo_details['ssh_url'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
