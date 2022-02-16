<?php

namespace UnbLibraries\SystemsToolkit\Git;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;

/**
 * Base class for GitRepo object. Wrapper for Cz\Git\GitRepository.
 */
class GitRepo {

  /**
   * The temporary path used to operate on this repo.
   *
   * @var string
   */
  protected $tmpDir;

  /**
   * The repository object.
   *
   * @var \CzProject\GitPhp\GitRepository
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

  /**
   * Create and set the temporary directory location.
   */
  protected function setTempDir() {
    $tempfile = tempnam(sys_get_temp_dir(), 'syskit_');
    if (file_exists($tempfile)) {
      unlink($tempfile);
    }
    mkdir($tempfile);
    $this->tmpDir = $tempfile;
  }

  /**
   * Get the temporary directory location for the repository.
   */
  public function getTmpDir() {
    return $this->tmpDir;
  }

  /**
   * Get a specific commit from the repository.
   *
   * @param string $num
   *   The index of the commit, typically a number.
   *
   * @return array
   *   The commit information array.
   */
  public function getCommit($num) {
    return $this->commits[$num];
  }

  /**
   * Set up the object from a clone to a local, temporary directory.
   *
   * @param string $repo_url
   *   The github clone URL of the repository.
   *
   * @throws \Exception
   */
  private function setCloneToTempDir($repo_url) {
    $this->setTempDir();
    $git = new Git();
    $this->repo = $git->cloneRepository(
      $repo_url,
      $this->tmpDir
    );
    $this->setBranches();
    $this->setCommits();
  }

  /**
   * Factory to create this object from a github clone URL.
   *
   * @param string $repo_url
   *   The github clone URL of the repository.
   *
   * @throws \Exception
   *
   * @return \UnbLibraries\SystemsToolkit\Git\GitRepo
   *   The GitRepo object.
   */
  public static function setCreateFromClone($repo_url) {
    $repo = new static();
    $repo->setCloneToTempDir($repo_url);
    return $repo;
  }

  /**
   * Set the list of branches in the object property.
   */
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

  /**
   * Get the commit message for a specific commit.
   *
   * @param string $hash
   *   The hash of the commit to check for.
   *
   * @return mixed
   *   The commit message, FALSE if the commit does not exist in the repository.
   */
  public function getCommitMessage($hash) {
    foreach ($this->commits as $commit) {
      if ($commit['hash'] == $hash) {
        return $commit['message'];
      }
    }
    return FALSE;
  }

  /**
   * Set the list of commits in the object property.
   */
  private function setCommits() {
    $commits = $this->repo->execute(
      [
        'log',
        '--all',
        '--decorate=short',
        '--pretty=format:"%H|%d|%s|%an"',
      ]
    );

    $last_decorator = '';
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
