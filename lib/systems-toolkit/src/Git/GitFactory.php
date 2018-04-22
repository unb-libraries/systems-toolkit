<?php

namespace UnbLibraries\SystemsToolkit\Git;

use Cz\Git\GitRepository;

/**
 * Base class for GitFactory object. Helper for git repo interaction.
 */
class GitFactory {

  /**
   * The temporary path used to operate on this repo.
   *
   * @var string
   */
  protected $tmpDir;

  /**
   * The repository object.
   *
   * @var string
   */
  public $repo;

  /**
   * The repository commits.
   *
   * @var array
   */
  public $commits = [];

  /**
   * The repository branches.
   *
   * @var array
   */
  public $branches = [];

  protected function setTempDir() {
    $tempfile = tempnam(sys_get_temp_dir(), 'syskit_');
    if (file_exists($tempfile)) {
      unlink($tempfile);
    }
    mkdir($tempfile);
    $this->tmpDir = $tempfile;
  }

  public function getTmpDir() {
    return $this->tmpDir;
  }

  public function getCommit($num) {
    return $this->commits[$num];
  }

  private function setCloneToTempDir($repo_url) {
    $this->setTempDir();
    $this->repo = GitRepository::cloneRepository(
      $repo_url,
      $this->tmpDir
    );
    $this->setBranches();
    $this->setCommits();
  }

  public static function setCreateFromClone($repo_url) {
    $repo = new static();
    $repo->setCloneToTempDir($repo_url);
    return $repo;
  }

  private function setBranches() {
    foreach ($this->repo->getBranches() as $repo_branch) {
      if (strstr($repo_branch, 'HEAD')) {
        continue;
      }
      $repo_branch = trim(
        str_replace('remotes/origin/', '', $repo_branch)
      );

      if (in_array($repo_branch, $this->branches)) {
        continue;
      }

      $this->branches[] = $repo_branch;
    }
  }

  public function getCommitMessage($hash) {
    foreach ($this->commits as $commit) {
      if ($commit['hash'] == $hash) {
        return $commit['message'];
      }
    }
    return FALSE;
  }

  private function setCommits() {
    $commits = $this->repo->execute(
      [
        'log',
        '--all',
        '--decorate=short',
        '--pretty=format:"%H|%d|%s|%an"',
      ]
    );

    $last_decorator = NULL;
    foreach ($commits as $commit) {
      $commit = str_replace('"', '', $commit);
      $commit_data = explode('|', $commit);

      if (empty($commit_data[1])) {
        $decorator = $last_decorator;
      }
      else {
        $decorator = $commit_data[1];
        $last_decorator = $decorator;
      }

      $this->commits[] = [
        'hash' => $commit_data[0],
        'branch' => $decorator,
        'message' => $commit_data[2],
        'author' => $commit_data[3],
      ];
    }
  }

}
