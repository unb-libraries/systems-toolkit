<?php

namespace UnbLibraries\SystemsToolkit;

use Symfony\Component\Console\Helper\Table;
use UnbLibraries\SystemsToolkit\Git\GitRepo;

/**
 * Trait for interacting with git repos.
 */
trait GitTrait {

  /**
   * Renders a list of commits from the repository in table format.
   *
   * @param \UnbLibraries\SystemsToolkit\Git\GitRepo $repo
   *   The repository to check for the commit.
   * @param int $number
   *   The hash of the commit to check for. Defaults to 10.
   */
  protected function getCommitListTable(GitRepo $repo, int $number = 10) {
    $wrapped_rows = [];

    foreach ($repo->commits as $commit) {
      $wrapped_rows[] = [
        $commit['hash'],
        $commit['message'],
      ];
    }

    $table = new Table($this->output());
    $table->setHeaders(['Commit Hash', 'Message'])
      ->setRows(array_slice($wrapped_rows, 0, $number));
    $table->render();
  }

  /**
   * Verifies a commit exists in a repository, otherwise throw an exception.
   *
   * @param \UnbLibraries\SystemsToolkit\Git\GitRepo $repo
   *   The repository to check for the commit.
   * @param string $hash
   *   The hash of the commit to check for.
   *
   * @throws \Exception
   */
  protected function getRepoHasCommit(GitRepo $repo, string $hash) {
    if (!$this->getCommitInRepo($repo, $hash)) {
      throw new \Exception(sprintf('The hash [%s] was not found in any branch of the repository.', $hash));
    }
  }

  /**
   * Checks if a commit exists in a repository.
   *
   * @param \UnbLibraries\SystemsToolkit\Git\GitRepo $repo
   *   The repository to check for the commit.
   * @param string $hash
   *   The hash of the commit to check for.
   *
   * @return bool
   *   TRUE if the commit exists in the repository. False otherwise.
   */
  private function getCommitInRepo(GitRepo $repo, string $hash) : bool {
    if (empty(trim($hash))) {
      $this->syskitIo->say('An empty hash was specified!');
      return FALSE;
    }
    if (empty($repo->commits)) {
      $this->syskitIo->say('The provided repository was either empty or had no commits.');
      return FALSE;
    }
    foreach ($repo->commits as $commit) {
      if ($hash == $commit['hash']) {
        return TRUE;
      }
    }
    $this->syskitIo->say(
      sprintf('The hash [%s] was not found in any branch of the repository.', $hash)
    );
    return FALSE;
  }

}
