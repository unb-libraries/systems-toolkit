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

}
