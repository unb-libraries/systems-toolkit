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

  public static function setCloneToTempDir($repo_url) {
    $repo = new static();
    $repo->setTempDir();
    $repo->repo = GitRepository::cloneRepository(
      $repo_url,
      $repo->tmpDir
    );
    return $repo;
  }


}
