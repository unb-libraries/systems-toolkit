<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use JiraRestApi\Configuration\ArrayConfiguration;
use Robo\Robo;

/**
 * Trait for interacting with our Jira instance.
 */
trait JiraTrait {

  /**
   * The config to use.
   *
   * @var \JiraRestApi\Configuration\ArrayConfiguration
   */
  protected $jiraConfig;

  /**
   * The jira server hostname.
   *
   * @var string
   */
  protected $jiraHostName;

  /**
   * The jira server user name to authenticate with.
   *
   * @var string
   */
  protected $jiraUserName;

  /**
   * The jira server user password to authenticate with.
   *
   * @var string
   */
  protected $jiraUserPassword;

  /**
   * Set the JIRA host from config.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function setJiraHost() {
    $this->jiraHostName = Robo::Config()->get('syskit.jira.hostName');
    if (empty($this->jiraHostName)) {
      throw new \Exception(sprintf('The Jira hostname has not been set in the configuration file. (jiraHostName)'));
    }
  }

  /**
   * Set the JIRA user from config.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function setJiraUser() {
    $this->jiraUserName = Robo::Config()->get('syskit.jira.userName');
    if (empty($this->jiraUserName)) {
      throw new \Exception(sprintf('The Jira username has not been set in the configuration file. (jiraUserName)'));
    }
  }

  /**
   * Set the JIRA pass.
   *
   * JIRA on-premises doesn't allow API keys to generate, so we need to password at run-time.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setJiraPass() {
    $this->jiraUserPassword = $this->askDefault(
      'Please enter the password for the user ' . $this->jiraUserName,
      'password'
    );
  }

  /**
   * Set config array.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setJiraConfig() {
    $this->jiraConfig = new ArrayConfiguration(
      array(
        'jiraHost' => $this->jiraHostName,
        'jiraUser' => $this->jiraUserName,
        'jiraPassword' => $this->jiraUserPassword,
      )
    );
  }

}
