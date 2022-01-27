<?php

namespace UnbLibraries\SystemsToolkit;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Project\ProjectService;
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
   * The jira server user password to authenticate with.
   *
   * @var object
   */
  protected $jiraService;

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
   * Set the JIRA service.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setJiraService() {
    $this->jiraService = new ProjectService($this->jiraConfig);
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
   * @hook pre-init
   */
  public function setJiraPass() {
    $this->jiraUserPassword = $this->ask(
      "Enter $this->jiraUserName's JIRA password for $this->jiraHostName"
    );
  }

  /**
   * Set config array.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setJiraConfig() {
    $this->jiraConfig = new ArrayConfiguration(
      [
        'jiraHost' => $this->jiraHostName,
        'jiraUser' => $this->jiraUserName,
        'jiraPassword' => $this->jiraUserPassword,
      ]
    );
  }

}