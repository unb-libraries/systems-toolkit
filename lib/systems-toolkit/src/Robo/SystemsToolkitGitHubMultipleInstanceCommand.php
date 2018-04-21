<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Github\ResultPager;
use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitGitHubCommand;

/**
 * Base class for SystemsToolkitGitHubMultipleInstanceCommand Robo commands.
 */
class SystemsToolkitGitHubMultipleInstanceCommand extends SystemsToolkitGitHubCommand {

  const ERROR_NO_ORGS = 'No organizations specified. Please provide them as a list!';
  const MESSAGE_CONFIRM_REPOSITORIES = 'The %s operation(s) will be applied to ALL of the above repositories. Are you sure you want to continue?';
  const MESSAGE_GETTING_REPO_LIST = 'Getting repository list for %s...';
  const MESSAGE_NAME_FILTERING_COMPLETE = 'Name filtering complete!';
  const MESSAGE_NAME_FILTERING_LIST = 'Name filtering repositories...';
  const MESSAGE_REPO_LIST_RETRIEVED = 'Repository List retrieved!';
  const MESSAGE_TOPIC_FILTERING_COMPLETE = 'Topic filtering complete!';
  const MESSAGE_TOPIC_FILTERING_LIST = 'Topic filtering repositories. This may take a while, particularly if you have not applied any name filters...';

  /**
   * The repositories to operate on.
   *
   * @var array
   */
  protected $repositories;

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
      $this->repositories
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
   * @param string $operation
   *   The operation string to display in the confirm message. Defaults to
   *   'operation'.
   *
   * @return bool
   *   TRUE if user agreed, FALSE otherwise.
   */
  protected function setConfirmRepositoryList(array $name_filters = [], array $topic_filters = [], array $filter_callbacks = [], $omit = [], $operation = 'operation') {
    $this->setRepositoryList($name_filters, $topic_filters, $filter_callbacks, $omit);
    $this->listRepositoryNames();
    return $this->confirm(sprintf(self::MESSAGE_CONFIRM_REPOSITORIES, $operation));
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
   */
  private function setRepositoryList(array $name_filters = [], array $topic_filters = [], array $filter_callbacks = [], $omit = []) {
    // Check for insanity.
    if (empty($this->organizations)) {
      $this->say(self::ERROR_NO_ORGS);
      return;
    }

    // Get all repositories org has.
    $org_list = implode(',', $this->organizations);
    $this->say(sprintf(self::MESSAGE_GETTING_REPO_LIST, $org_list));
    $paginator = new ResultPager($this->client);
    $organizationApi = $this->client->api('organization');
    $parameters = $this->organizations;
    $this->repositories = $paginator->fetchAll($organizationApi, 'repositories', $parameters);
    $this->say(self::MESSAGE_REPO_LIST_RETRIEVED);

    // Remove omissions.
    foreach ($this->repositories as $repository_index => $repository) {
      if (in_array($repository['name'], $omit)) {
        unset($this->repositories[$repository_index]);
      }
    }

    // Case : no filtering.
    if (empty($name_filters[0]) && empty($topic_filters[0])) {
      return;
    }

    // Perform name filtering first. This may reduce topic API calls later.
    if (!empty($name_filters[0])) {
      $this->say(self::MESSAGE_NAME_FILTERING_LIST);
      foreach ($this->repositories as $repository_index => $repository) {
        if (!$this->instanceNameMatchesSearchTerms($name_filters, $repository['name'])) {
          unset($this->repositories[$repository_index]);
        }
      }
      $this->say(self::MESSAGE_NAME_FILTERING_COMPLETE);
    }

    // Perform topic filtering.
    if (!empty($topic_filters[0])) {
      $this->say(self::MESSAGE_TOPIC_FILTERING_LIST);
      foreach ($this->repositories as $repository_index => $repository) {
        $repo_topics = $this->client->api('repo')->topics($repository['owner']['login'], $repository['name'])['names'];
        if (empty(array_intersect($repo_topics, $topic_filters))) {
          unset($this->repositories[$repository_index]);;
        }
      }
      $this->say(self::MESSAGE_TOPIC_FILTERING_COMPLETE);
    }

    // Pedantically rekey the repositories array.
    $this->repositories = array_values($this->repositories);
  }

}
