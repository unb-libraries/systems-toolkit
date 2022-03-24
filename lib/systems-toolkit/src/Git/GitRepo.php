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
  protected string $tmpDir;

  /**
   * The repository object.
   *
   * @var \CzProject\GitPhp\GitRepository
   */
  public GitRepository $repo;

  /**
   * The repository commits.
   *
   * @var array
   */
  public array $commits = [];

  /**
   * The repository branches.
   *
   * @var array
   */
  public array $branches = [];

  /**
   * Creates and sets the temporary directory location.
   *
   * @param string $tmp_root
   *   Optional, the root path for the temp dir. Optional, defaults to system.
   */
  protected function setTempDir(string $tmp_root = '') {
    if (empty($tmp_root)) {
      $tmp_root = sys_get_temp_dir();
    }
    $tempfile = tempnam($tmp_root, 'syskit_');
    if (file_exists($tempfile)) {
      unlink($tempfile);
    }
    mkdir($tempfile);
    $this->tmpDir = $tempfile;
  }

  /**
   * Gets the temporary directory location for the repository.
   *
   * @return string
   *   The temporary directory.
   */
  public function getTmpDir() : string {
    return $this->tmpDir;
  }

  /**
   * Gets a specific commit from the repository.
   *
   * @param string $num
   *   The index of the commit, typically a number.
   *
   * @return array
   *   The commit information array.
   */
  public function getCommit(string $num) : array {
    return $this->commits[$num];
  }

  /**
   * Sets up the object from a clone to a local, temporary directory.
   *
   * @param string $repo_url
   *   The GitHub clone URL of the repository.
   * @param string $tmp_root
   *   Optional, the root path for the temp dir. Optional, defaults to system.
   *
   * @throws \Exception
   */
  private function setCloneToTempDir(string $repo_url, string $tmp_root = '') {
    $this->setTempDir($tmp_root);
    $git = new Git();
    $this->repo = $git->cloneRepository(
      $repo_url,
      $this->tmpDir
    );
    $this->setBranches();
    $this->setCommits();
  }

  /**
   * Creates this object from a GitHub clone URL.
   *
   * @param string $repo_url
   *   The GitHub clone URL of the repository.
   * @param string $tmp_root
   *   Optional, the root path for the temp dir. Optional, defaults to system.
   *
   * @throws \Exception
   *
   * @return \UnbLibraries\SystemsToolkit\Git\GitRepo
   *   The GitRepo object.
   */
  public static function setCreateFromClone(
    string $repo_url,
    string $tmp_root = ''
  ) : self {
    $repo = new static();
    $repo->setCloneToTempDir($repo_url, $tmp_root);
    return $repo;
  }

  /**
   * Sets the list of branches in the object property.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  private function setBranches() {
    foreach ($this->repo->getBranches() as $repo_branch) {
      if (str_contains($repo_branch, 'HEAD')) {
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
   * Gets the commit message for a specific commit.
   *
   * @param string $hash
   *   The hash of the commit to check for.
   *
   * @return string
   *   The commit message, FALSE if the commit does not exist in the repository.
   */
  public function getCommitMessage(string $hash) : string {
    foreach ($this->commits as $commit) {
      if ($commit['hash'] == $hash) {
        return $commit['message'];
      }
    }
    return '';
  }

  /**
   * Sets the list of commits in the object property.
   *
   * @throws \CzProject\GitPhp\GitException
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
