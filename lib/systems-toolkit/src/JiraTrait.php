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
  protected ArrayConfiguration $jiraConfig;

  /**
   * The JIRA server hostname.
   *
   * @var string
   */
  protected string $jiraHostName;

  /**
   * The JIRA server username to authenticate with.
   *
   * @var string
   */
  protected string $jiraUserName;

  /**
   * The JIRA server user password to authenticate with.
   *
   * @var string
   */
  protected string $jiraUserPassword;

  /**
   * The JIRA server user password to authenticate with.
   *
   * @var object
   */
  protected object $jiraService;

  /**
   * Sets the JIRA host from config.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function setJiraHost() {
    $this->jiraHostName = Robo::Config()->get('syskit.jira.hostName');
    if (empty($this->jiraHostName)) {
      throw new \Exception('The Jira hostname has not been set in the configuration file. (jiraHostName)');
    }
  }

  /**
   * Sets the JIRA service.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setJiraService() {
    $this->jiraService = new ProjectService($this->jiraConfig);
  }

  /**
   * Sets the JIRA user from config.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function setJiraUser() {
    $this->jiraUserName = Robo::Config()->get('syskit.jira.userName');
    if (empty($this->jiraUserName)) {
      throw new \Exception('The Jira username has not been set in the configuration file. (jiraUserName)');
    }
  }

  /**
   * Sets the JIRA pass.
   *
   * @description JIRA on-premises doesn't allow API keys to generate,
   * so we need to password at run-time.
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
   * Sets the config array.
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
