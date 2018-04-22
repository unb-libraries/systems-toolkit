<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use Symfony\Component\Console\Helper\Table;

/**
 * Base class for SystemsToolkitGitCommand Robo commands.
 */
class SystemsToolkitGitCommand extends SystemsToolkitCommand {

  const MESSAGE_EMPTY_HASH = 'An empty hash was specified!';
  const MESSAGE_HASH_NOT_FOUND = 'The hash [%s] was not found in any branch of the repository.';
  const MESSAGE_COMMITS_NOT_FOUND = 'The provided repository was either empty or had no commits.';

  private function getCommitInRepo($repo, $hash) {
    if (empty(trim($hash))) {
      $this->say(self::MESSAGE_EMPTY_HASH);
      return FALSE;
    }
    if (empty($repo->commits)) {
      $this->say(self::MESSAGE_COMMITS_NOT_FOUND);
      return FALSE;
    }
    foreach ($repo->commits as $commit) {
      if ($hash == $commit['hash']) {
        return TRUE;
      }
    }
    $this->say(
      sprintf(self::MESSAGE_HASH_NOT_FOUND, $hash)
    );
    return FALSE;
  }

  protected function getRepoHasCommit($repo, $hash) {
    if (!$this->getCommitInRepo($repo, $hash)) {
      throw new \Exception(sprintf(self::MESSAGE_HASH_NOT_FOUND, $hash));
    }
  }

  protected function getCommitListTable($repo, $number = 10) {
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

}
