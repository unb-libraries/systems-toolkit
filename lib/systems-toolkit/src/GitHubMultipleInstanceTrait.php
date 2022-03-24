<?php

namespace UnbLibraries\SystemsToolkit;

use Github\ResultPager;
use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\GitHubTrait;
use UnbLibraries\SystemsToolkit\GitTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitGitHubCommand;

/**
 * Trait for interacting with multiple instance repositories in GitHub.
 */
trait GitHubMultipleInstanceTrait {

  use GitTrait;
  use GitHubTrait;

  /**
   * The repository commits.
   *
   * @var array
   */
  protected array $failedRepos = [];

  /**
   * The repositories to operate on.
   *
   * @var array
   */
  protected array $githubRepositories;

  /**
   * Sets whether changes to the current repo are pushed.
   *
   * @var bool
   */
  protected bool $repoChangesPushed = FALSE;

  /**
   * The repository commits.
   *
   * @var array
   */
  protected array $successfulRepos = [];

  /**
   * Stores the list of repositories to operate on and confirms list with user.
   *
   * @param array $name_filters
   *   Only repositories whose names contain one of $name_filters values will be
   *   returned. Optional.
   * @param array $topic_filters
   *   Only repositories whose topics contain one of $topic_filters values
   *   exactly will be stored. Optional.
   * @param array $callback_filters
   *   Only repositories whose filter callbacks functions provided here return
   *   TRUE will be stored. Optional.
   * @param array $omit
   *   An array of repository names to omit from the list.
   * @param string $operation
   *   The operation string to display in the confirm message. Defaults to
   *   'operation'.
   * @param bool $no_confirm
   *   TRUE if all confirmations be assumed yes.
   *
   * @return bool
   *   TRUE if user agreed, FALSE otherwise.
   */
  protected function setConfirmRepositoryList(
    array $name_filters = [],
    array $topic_filters = [],
    array $callback_filters = [],
    array $omit = [],
    string $operation = 'operation',
    bool $no_confirm = FALSE
  ) : bool {
    $this->setRepositoryList($name_filters, $topic_filters, $callback_filters, $omit);

    // Optionally filter them.
    if (!$no_confirm) {
      $this->listRepositoryNames();
      $need_remove = $this->confirm('Would you like to remove any instances?');
    }
    else {
      $need_remove = FALSE;
    }

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

    if (!$no_confirm) {
      return $this->confirm(sprintf('The %s operation(s) will be applied to ALL of the above repositories. Are you sure you want to continue?', $operation));
    }
    else {
      return TRUE;
    }
  }

  /**
   * Gets the list of repositories to operate on from GitHub and filter them.
   *
   * @param array $name_filters
   *   Only repositories whose names contain one of $name_filters values will be
   *   returned. Optional.
   * @param array $topic_filters
   *   Only repositories whose topics contain one of $topic_filters values
   *   exactly will be stored. Optional.
   * @param array $callback_filters
   *   Only repositories whose filter callbacks functions provided here return
   *   TRUE will be stored. Optional.
   * @param array $omit
   *   An array of repository names to omit from the list.
   */
  private function setRepositoryList(
    array $name_filters = [],
    array $topic_filters = [],
    array $callback_filters = [],
    array $omit = []
  ) {
    $this->populateGitHubRepositoryList();

    // Remove omissions.
    foreach ($this->githubRepositories as $repository_index => $repository) {
      if (in_array($repository['name'], $omit)) {
        unset($this->githubRepositories[$repository_index]);
      }
    }

    // Case : no filtering.
    if (empty($name_filters[0]) && empty($topic_filters[0]) && empty($callback_filters[0])) {
      return;
    }

    // Filter repositories. Place these in order of least intensive to most!
    $this->filterRepositoriesByName($name_filters);
    $this->filterRepositoriesByCallback($callback_filters);
    $this->filterRepositoriesByTopic($topic_filters);

    // If we have any repositories left, pedantically rekey the array.
    $this->githubRepositories = array_values($this->githubRepositories);
  }

  /**
   * Populates the repository list with all organizational repositories.
   */
  private function populateGitHubRepositoryList() {
    // Check for config based insanity.
    if (empty($this->organizations)) {
      $this->say('No organizations specified in syskit_config.yml. Please provide them as a list!');
      exit;
    }

    $org_list = implode(',', $this->organizations);
    $this->say(sprintf('Getting repository list for %s...', $org_list));
    $paginator = new ResultPager($this->client);
    $organizationApi = $this->client->api('organization');
    $parameters = $this->organizations;
    $this->githubRepositories = $paginator->fetchAll($organizationApi, 'repositories', $parameters);
    usort($this->githubRepositories, fn($a, $b) => strcmp($a['name'], $b['name']));
    $this->say('Repository List retrieved!');
  }

  /**
   * Filters the repository list based on results of user-provided callbacks.
   *
   * @param string[] $callback_filters
   *   An array of callback names to execute. Callbacks returning FALSE indicate
   *   to remove the item.
   */
  private function filterRepositoriesByCallback(array $callback_filters) {
    if (!empty($callback_filters[0])) {
      $this->say('Callback filtering repositories...');
      foreach ($this->githubRepositories as $repository_index => $repository) {
        foreach ($callback_filters as $callback_filter) {
          if (!call_user_func($callback_filter, $repository)) {
            unset($this->githubRepositories[$repository_index]);
            break;
          }
        }
      }
      $this->say('Callback filtering complete!');
    }
  }

  /**
   * Filters the repository list based on their names.
   *
   * @param string[] $name_filters
   *   An array of keywords to compare against repository names. Repositories
   *   that do not match any keywords will be removed.
   */
  private function filterRepositoriesByName(array $name_filters) {
    if (!empty($name_filters[0])) {
      $this->say('Name filtering repositories...');
      foreach ($this->githubRepositories as $repository_index => $repository) {
        if (!static::instanceNameMatchesSearchTerms($name_filters, $repository['name'])) {
          unset($this->githubRepositories[$repository_index]);
        }
      }
      $this->say('Name filtering complete!');
    }
  }

  /**
   * Filters the repository list based on their GitHub topics.
   *
   * @param string[] $topic_filters
   *   An array of keywords to compare against repository topics. Repositories
   *   that do not match any of the topics will be filtered.
   */
  private function filterRepositoriesByTopic(array $topic_filters) {
    if (!empty($topic_filters[0])) {
      $this->say('Topic filtering repositories...');
      foreach ($this->githubRepositories as $repo_idx => $repo) {
        // This assumes an AND filter for multiple repo topics.
        if (!count(array_intersect($repo['topics'], $topic_filters)) == count($topic_filters)) {
          unset($this->githubRepositories[$repo_idx]);
        }
      }
      $this->say('Topic filtering complete!');
    }
  }

  /**
   * Determines if a repository name partially matches multiple terms.
   *
   * @param array $terms
   *   An array of terms to match in a case-insensitive manner against the name.
   * @param string $name
   *   The name to match against.
   *
   * @return bool
   *   TRUE if the name matches one of the terms. FALSE otherwise.
   */
  public static function instanceNameMatchesSearchTerms(array $terms, string $name) : bool {
    foreach ($terms as $match) {
      if (stristr($name, (string) $match)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Outputs a formatted list of repositories set to operate on to the console.
   */
  protected function listRepositoryNames() {
    $wrapped_rows = array_map(
      fn($el) => [$el['name']],
      $this->githubRepositories
    );
    $table = new Table($this->output());
    $table->setHeaders(['Repository Name'])
      ->setRows($wrapped_rows);
    $table->setStyle('borderless');
    $table->render();
  }

}
