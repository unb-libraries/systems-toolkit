<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\DomCrawler\Crawler;
use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\Drupal8ModuleCommand;
use UnbLibraries\SystemsToolkit\Robo\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Robo\KubeExecTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\TravisExecTrait;

/**
 * Class for Drupal8UpdatesCommand Robo commands.
 */
class Drupal8UpdateGitHubActionsCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;
  use KubeExecTrait;
  use TravisExecTrait;

  const MESSAGE_CHECKING_OUT_REPO = 'Cloning %s repository to temporary folder...';

  /**
   * Should confirmations for this object be skipped?
   *
   * @var bool
   */
  private $noConfirm = FALSE;

  /**
   * Should only security updates be listed/applied?
   *
   * @var bool
   */
  private $securityOnly = FALSE;

  /**
   * A list of required updates in a tabular format.
   *
   * @var string[]
   */
  private $tabulatedUpdates = [];

  /**
   * A list of required updates .
   *
   * @var string[]
   */
  private $updates = [];

  /**
   * Perform needed Drupal 8 updates automatically.
   *
   * @option namespaces
   *   The extensions to match when finding files. Defaults to dev only.
   * @option array only-update
   *   A comma separated list of modules to query. Defaults to all.
   * @option bool security-only
   *   Only perform security updates.
   * @option bool yes
   *   Assume a 'yes' answer for all prompts.
   * @option int multi-repo-delay
   *   The amount of time to delay between updating repositories.
   *
   * @throws \Exception
   *
   * @command drupal:8:update-actions-workflow
   */
  public function setDoDrupal8Updates($options = ['namespaces' => ['dev'], 'only-update' => [], 'security-only' => FALSE, 'yes' => FALSE, 'multi-repo-delay' => '240']) {
    $this->say('Updates needed, querying corresponding repositories in GitHub');
    $continue = $this->setConfirmRepositoryList(
      [],
      ['drupal8'],
      [],
      [],
      'Update Github Actions Workflow',
      TRUE
    );

    if ($continue) {
      $this->updateAllRepositories($options);
    }
  }

  /**
   * Update all queued GitHub repositories.
   */
  private function updateAllRepositories($options) {
    foreach($this->githubRepositories as $repository) {
      $this->updateRepository($repository);
      $this->say("Sleeping for {$options['multi-repo-delay']} seconds to spread build times...");
      sleep($options['multi-repo-delay']);
    }
  }

  /**
   * Update a specific GitHub repository with required updates.
   *
   * @param array $repository
   *   The associative array describing the repository.
   */
  private function updateRepository(array $repository) {
    foreach ($this->githubRepositories as $repository_data) {
      // Pull down.
      $this->say(
        sprintf(
          self::MESSAGE_CHECKING_OUT_REPO,
          $repository_data['name']
        )
      );
      $repo = GitRepo::setCreateFromClone($repository_data['ssh_url']);
      $repo->repo->checkout('dev');
      $repo_path = $repo->repo->getRepositoryPath();
      passthru("cd $repo_path; composer install; ./vendor/bin/dockworker dockworker:gh-actions:update; git add .github/workflows/test-suite.yaml; git commit --no-verify -m 'Update GitHub Actions workflow'; git push origin dev");
    }
  }

}
