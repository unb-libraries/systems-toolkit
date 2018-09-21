<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Github\ResultPager;
use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitGitHubCommand;
use UnbLibraries\SystemsToolkit\Robo\GitTrait;
use UnbLibraries\SystemsToolkit\Robo\GitHubTrait;

/**
 * Trait for interacting with multiple instance repositories in GitHub.
 */
trait GitHubMultipleInstanceTrait {

  use GitTrait;
  use GitHubTrait;

  /**
   * The repositories to operate on.
   *
   * @var array
   */
  protected $githubRepositories;

  /**
   * The repository commits.
   *
   * @var array
   */
  protected $successfulRepos = [];

  /**
   * The repository commits.
   *
   * @var array
   */
  protected $failedRepos = [];

  /**
   * Determine if a repository name partially matches multiple terms.
   *
   * @param array $terms
   *   An array of terms to match in a case insensitive manner against the name.
   * @param string $name
   *   The name to match against.
   *
   * @return bool
   *   TRUE if the name matches one of the terms. FALSE otherwise.
   */
  public static function instanceNameMatchesSearchTerms(array $terms, $name) {
    foreach ($terms as $match) {
      if (stristr($name, $match)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Output a formatted list of repositories set to operate on to the console.
   */
  protected function listRepositoryNames() {
    $wrapped_rows = array_map(
      function ($el) {
        return array($el['name']);
      },
      $this->githubRepositories
    );
    $table = new Table($this->output());
    $table->setHeaders(['Repository Name'])
      ->setRows($wrapped_rows);
    $table->setStyle('borderless');
    $table->render();
  }

  /**
   * Store the list of repositories to operate on and confirm list with user.
   *
   * @param array $name_filters
   *   Only repositories whose names contain one of $name_filters values will be
   *   returned. Optional.
   * @param array $topic_filters
   *   Only repositories whose topics contain one of $topic_filters values
   *   exactly will be stored. Optional.
   * @param array $filter_callbacks
   *   Only repositories whose filter callbacks functions provided here return
   *   TRUE will be stored. Optional.
   * @param array $omit
   *   An array of repository names to omit from the list.
   * @param string $operation
   *   The operation string to display in the confirm message. Defaults to
   *   'operation'.
   *
   * @return bool
   *   TRUE if user agreed, FALSE otherwise.
   */
  protected function setConfirmRepositoryList(array $name_filters = [], array $topic_filters = [], array $filter_callbacks = [], array $omit = [], $operation = 'operation') {
    $this->setRepositoryList($name_filters, $topic_filters, $filter_callbacks, $omit);
    $this->listRepositoryNames();

    // Optionally filter them.
    $need_remove = $this->confirm('Would you like to remove any instances?');
    while ($need_remove == TRUE) {
      $to_remove = $this->ask('Which ones? (Specify Name, Comma separated list)');
      if (!empty($to_remove)) {
        $removes = explode(',', $to_remove);
        foreach ($this->githubRepositories as $repo_index => $repository) {
          if (in_array($repository['name'], $removes)) {
            $this->say("Removing {$repository['name']} from list");
            unset($this->githubRepositories[$repo_index]);
          }
        }
      }
      $this->listRepositoryNames();
      $need_remove = $this->confirm('Would you like to remove any more instances?');
    }

    return $this->confirm(sprintf('The %s operation(s) will be applied to ALL of the above repositories. Are you sure you want to continue?', $operation));
  }

  /**
   * Get the list of repositories to operate on from GitHub and filter them.
   *
   * @param array $name_filters
   *   Only repositories whose names contain one of $name_filters values will be
   *   returned. Optional.
   * @param array $topic_filters
   *   Only repositories whose topics contain one of $topic_filters values
   *   exactly will be stored. Optional.
   * @param array $filter_callbacks
   *   Only repositories whose filter callbacks functions provided here return
   *   TRUE will be stored. Optional.
   * @param array $omit
   *   An array of repository names to omit from the list.
   *
   * @TODO : Add Callback filtering from $filter_callbacks.
   */
  private function setRepositoryList(array $name_filters = [], array $topic_filters = [], array $filter_callbacks = [], array $omit = []) {
    // Check for insanity.
    if (empty($this->organizations)) {
      $this->say('No organizations specified. Please provide them as a list!');
      return;
    }

    // Get all organization(s) repositories.
    $org_list = implode(',', $this->organizations);
    $this->say(sprintf('Getting repository list for %s...', $org_list));
    $paginator = new ResultPager($this->client);
    $organizationApi = $this->client->api('organization');
    $parameters = $this->organizations;
    $this->githubRepositories = $paginator->fetchAll($organizationApi, 'repositories', $parameters);
    $this->say('Repository List retrieved!');

    // Remove omissions.
    foreach ($this->githubRepositories as $repository_index => $repository) {
      if (in_array($repository['name'], $omit)) {
        unset($this->githubRepositories[$repository_index]);
      }
    }

    // Case : no filtering.
    if (empty($name_filters[0]) && empty($topic_filters[0])) {
      return;
    }

    // Perform name filtering first. This may reduce topic API calls later.
    if (!empty($name_filters[0])) {
      $this->say('Name filtering repositories...');
      foreach ($this->githubRepositories as $repository_index => $repository) {
        if (!$this->instanceNameMatchesSearchTerms($name_filters, $repository['name'])) {
          unset($this->githubRepositories[$repository_index]);
        }
      }
      $this->say('Name filtering complete!');
    }

    // Perform topic filtering.
    if (!empty($topic_filters[0])) {
      $this->say('Topic filtering repositories. This may take a while, particularly if you have not applied any name filters...');
      foreach ($this->githubRepositories as $repository_index => $repository) {
        $repo_topics = $this->client->api('repo')->topics($repository['owner']['login'], $repository['name'])['names'];
        if (empty(array_intersect($repo_topics, $topic_filters))) {
          unset($this->githubRepositories[$repository_index]);;
        }
      }
      $this->say('Topic filtering complete!');
    }

    // Pedantically rekey the repositories array.
    $this->githubRepositories = array_values($this->githubRepositories);
  }

}