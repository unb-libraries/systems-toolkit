<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use UnbLibraries\SystemsToolkit\GitHubMultipleInstanceTrait;
use UnbLibraries\SystemsToolkit\Git\GitRepo;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for KubernetesMetadataRepoCommand Robo commands.
 */
class KubernetesMetadataRepoCommand extends SystemsToolkitCommand {

  use GitHubMultipleInstanceTrait;

  /**
   * Defines the deploy environments to iterate over when comparing files.
   */
  public const DEPLOY_ENVS = [
    'dev',
    'prod',
  ];

  /**
   * Defines the header to use during file diff output.
   */
  public const DIFF_HEADER = "+++ Central Repo\n--- Lean Repo\n";

  /**
   * Defines the format of the docker repository/image names.
   */
  public const DOCKER_IMAGE_FORMAT = 'ghcr.io/unb-libraries/%s';

  /**
   * Defines the GitHub repository to use as the 'central' metadata repository.
   */
  public const CENTRAL_METADATA_REPO = 'git@github.com:unb-libraries/kubernetes-metadata.git';

  /**
   * Defines the branch of the 'lean' repositories to compare against.
   */
  public const LEAN_REPO_BRANCH = 'dev';

  /**
   * Indicates the placeholder that is used for images in lean repos.
   */
  public const LEAN_REPO_IMAGE_PLACEHOLDER = '||DEPLOYMENTIMAGE||';

  /**
   * Indicates the path in lean repositories where the metadata files are found.
   */
  public const LEAN_REPO_METADATA_PATH = '.dockworker/deployment/k8s';

  /**
   * Translates between filenames in lean repositories and the central one.
   */
  public const LEAN_CENTRAL_FILENAME_TRANSLATION = [
    'backup' => 'Backup',
    'cronjob' => 'CronJob',
    'deployment' => 'Deployment',
  ];

  /**
   * The current central metadata file being audited.
   *
   * @var string
   */
  protected string $curCentralMetadataFile;

  /**
   * Does the current central metadata file exist?
   *
   * @var bool
   */
  protected bool $curCentralMetadataFileExists = FALSE;

  /**
   * The contents of the current central metadata file.
   *
   * @var string
   */
  protected string $curCentralMetadataFileContents;

  /**
   * The current docker image.
   *
   * @var string
   */
  protected string $curDockerImage;

  /**
   * The current deployment env.
   *
   * @var string
   */
  protected string $curDeployEnv;

  /**
   * The current metadata file slug to output.
   *
   * @var string
   */
  protected string $curFileSlug;

  /**
   * The current lean metadata file being audited.
   *
   * @var string
   */
  protected string $curLeanMetadataFile;

  /**
   * Does the current lean metadata file exist?
   *
   * @var bool
   */
  protected bool $curLeanMetadataFileExists = FALSE;

  /**
   * The contents of the current lean metadata file.
   *
   * @var string
   */
  protected string $curLeanMetadataFileContents;

  /**
   * The current lean repo being audited.
   *
   * @var array
   */
  protected array $curLeanRepo;

  /**
   * The current lean repo being audited.
   *
   * @var \UnbLibraries\SystemsToolkit\Git\GitRepo
   */
  protected GitRepo $curLeanRepoClone;

  /**
   * Flag if the current lean repo needs push.
   *
   * @var bool
   */
  protected bool $curLeanRepoNeedsPush;

  /**
   * The current lean repo being audited.
   *
   * @var string
   */
  protected string $curLeanRepoSlug;

  /**
   * The current metadata type.
   *
   * @var string
   */
  protected string $curMetadataType;

  /**
   * The kubernetes metadata repo.
   *
   * @var \UnbLibraries\SystemsToolkit\Git\GitRepo
   */
  protected GitRepo $centralMetadataRepo;

  /**
   * Flag if the central metadata repo needs push.
   *
   * @var bool
   */
  protected bool $centralMetadataRepoNeedsPush = FALSE;

  /**
   * The name filter to match repositories against.
   *
   * @var string
   */
  protected string $nameFilter;

  /**
   * The tag filter to match repositories against.
   *
   * @var string
   */
  protected string $tagFilter;

  /**
   * Audits the kubernetes-metadata repo against lean repos.
   *
   * @param string $tag_filter
   *   The tag filter to apply when choosing the repositories to operate on.
   * @param string $name_filter
   *   The name filter to apply when choosing the repositories to operate on.
   * @param string[] $options
   *   The array of available CLI options.
   *
   * @option $central-repo-branch
   *   The central repository branch to audit against.
   * @option $yes
   *   Assume a 'yes' answer for all prompts.
   *
   * @command k8s:metadata:audit-repos
   * @usage 'drupal8' '' --yes
   * @usage '' 'pmportal.org' --yes
   *
   * @throws \Exception
   */
  public function setKubernetesMetadataServiceAudit(
    string $tag_filter,
    string $name_filter,
    array $options = [
      'central-repo-branch' => '1.23',
      'yes' => FALSE,
    ]
  ) : void {
    $this->options = $options;
    $this->nameFilter = $name_filter;
    $this->tagFilter = $tag_filter;
    $this->runKubernetesMetadataServiceAudit();
  }

  /**
   * Selects and audits lean repositories against the central repo.
   *
   * @throws \Exception
   */
  protected function runKubernetesMetadataServiceAudit() : void {
    $continue = $this->setConfirmRepositoryList(
      [$this->nameFilter],
      [$this->tagFilter],
      [],
      [],
      'Update Metadata Repository',
      $this->options['yes']
    );

    if ($continue) {
      $this->auditAllRepositories();
    }
  }

  /**
   * Audits all queued GitHub lean metadata repositories.
   *
   * @throws \Exception
   */
  protected function auditAllRepositories() : void {
    if (!empty($this->githubRepositories)) {
      // Instantiate local source repo.
      $this->say("Cloning central repo/{$this->options['central-repo-branch']}...");
      $this->centralMetadataRepo = GitRepo::setCreateFromClone(self::CENTRAL_METADATA_REPO, $this->tmpDir);
      $this->centralMetadataRepo->repo->checkout($this->options['central-repo-branch']);
    }

    foreach ($this->githubRepositories as $repository) {
      $this->curLeanRepo = $repository;
      $this->auditRepository();

      if ($this->curLeanRepoNeedsPush) {
        $this->say('Pushing lean repo changes to GitHub...');
        $this->pushCurLeanRepo();
        $this->say('Done!');
      }
      else {
        $this->say('No differences found!');
      }
      $this->io()->newLine();
    }

    if ($this->centralMetadataRepoNeedsPush) {
      $this->say('Pushing central repo changes to GitHub...');
      $this->pushCentralRepo();
      $this->say('Done!');
    }
    $this->io()->newLine();
  }

  /**
   * Audits the lean GitHub repository against the central metadata repo.
   *
   * @throws \Exception
   */
  protected function auditRepository() : void {
    try {
      $this->initRepositoryAudit();
    }
    catch (\Exception $e) {
      $this->io()->warning("Error initializing lean repo {$this->curLeanRepo['name']} default branch [$e]");
      return;
    }

    foreach (self::DEPLOY_ENVS as $deploy_env) {
      $this->curDeployEnv = $deploy_env;
      foreach (array_keys(self::LEAN_CENTRAL_FILENAME_TRANSLATION) as $metadata_type) {
        $this->curMetadataType = $metadata_type;
        $this->curFileSlug = "$this->curMetadataType.$this->curDeployEnv";
        $this->setLeanMetadataFile();
        $this->setCentralMetadataFile();

        // Case : both central and lean repo have the file.
        if ($this->curLeanMetadataFileExists && $this->curCentralMetadataFileExists) {
          $this->say("Comparing Files: $this->curFileSlug...");
          $diff_contents = $this->diffRepositoryFiles();
          if ($diff_contents != self::DIFF_HEADER) {
            $this->io()->warning("$this->curFileSlug Files Differ!");
            $this->io()->block($diff_contents);
            $this->setCanonicalMetadataFile();
          }
        }

        // @todo Other cases - lean repo has file but not central, etc.
      }
    }
  }

  /**
   * Initializes the information needed to audit the repository against central.
   *
   * @throws \Exception
   */
  protected function initRepositoryAudit() : void {
    $this->curLeanRepoNeedsPush = FALSE;
    $this->io()->title($this->curLeanRepo['name']);
    $this->say('Cloning lean repo...');
    $this->curLeanRepoClone = GitRepo::setCreateFromClone($this->curLeanRepo['ssh_url'], $this->tmpDir);
    $this->curLeanRepoClone->repo->checkout(self::LEAN_REPO_BRANCH);
    $this->setCurDockerImage();
    $this->setCurLeanRepoSlug();
  }

  /**
   * Sets the current docker image.
   */
  protected function setCurDockerImage() : void {
    $this->curDockerImage = sprintf(
      self::DOCKER_IMAGE_FORMAT,
      $this->curLeanRepo['name']
    );
  }

  /**
   * Sets the lean repo slug name.
   */
  protected function setCurLeanRepoSlug() : void {
    $this->curLeanRepoSlug = self::slugifyName($this->curLeanRepo['name']);
  }

  /**
   * Gets the repository name, slugified.
   *
   * @param string $name
   *   The repository name to slugify.
   *
   * @return string
   *   The name, slugified.
   */
  private static function slugifyName(string $name) : string {
    return str_replace(['.'], ['-'], $name);
  }

  /**
   * Sets the current audit file from the lean metadata repo.
   */
  protected function setLeanMetadataFile() : void {
    $file_path = implode(
        '/',
        [
          $this->curLeanRepoClone->getTmpDir(),
          self::LEAN_REPO_METADATA_PATH,
          $this->curDeployEnv,
              $this->curMetadataType,
        ]
      ) .
      '.yaml';
    $this->curLeanMetadataFile = $file_path;
    $this->curLeanMetadataFileExists = file_exists($this->curLeanMetadataFile);

    if ($this->curLeanMetadataFileExists) {
      $this->curLeanMetadataFileContents = file_get_contents($this->curLeanMetadataFile);
      $this->curLeanMetadataFileContents = str_replace(self::LEAN_REPO_IMAGE_PLACEHOLDER, $this->curDockerImage . ':' . $this->curDeployEnv, $this->curLeanMetadataFileContents);
    }
    else {
      $this->curLeanMetadataFileContents = '';
    }
  }

  /**
   * Sets the current audit file from the central metadata repo.
   */
  protected function setCentralMetadataFile() : void {
    if ($this->curMetadataType == 'backup') {
      if ($this->curDeployEnv == 'prod') {
        $file_path = implode(
            '/',
            [
              $this->centralMetadataRepo->getTmpDir(),
              'services',
              $this->curLeanRepoSlug,
              'backup',
              implode(
                '.',
                [
                  'backup-' . $this->curLeanRepoSlug,
                  'CronJob',
                  'prod',
                ]
              ),
            ]
          ) .
          '.yaml';
      }
    }
    else {
      $file_path = implode(
          '/',
          [
            $this->centralMetadataRepo->getTmpDir(),
            'services',
            $this->curLeanRepoSlug,
            $this->curDeployEnv,
            implode(
              '.',
              [
                $this->curLeanRepoSlug,
                self::LEAN_CENTRAL_FILENAME_TRANSLATION[$this->curMetadataType],
                $this->curDeployEnv,
              ]
            ),
          ]
        ) .
        '.yaml';
    }

    if (!empty($file_path)) {
      $this->curCentralMetadataFile = $file_path;
      $this->curCentralMetadataFileExists = file_exists($this->curCentralMetadataFile);

      if ($this->curCentralMetadataFileExists) {
        $this->curCentralMetadataFileContents = file_get_contents($this->curCentralMetadataFile);
      }
      else {
        $this->curCentralMetadataFileContents = '';
      }
    }
  }

  /**
   * Determines the difference between lean and central repositories for a file.
   *
   * @return string
   *   A unified diff of the contents, including a header.
   */
  protected function diffRepositoryFiles() : string {
    $diff_builder = new UnifiedDiffOutputBuilder(self::DIFF_HEADER);
    $differ = new Differ($diff_builder);
    return $differ->diff($this->curLeanMetadataFileContents, $this->curCentralMetadataFileContents);
  }

  /**
   * Chooses and commits the canonical metadata file.
   */
  protected function setCanonicalMetadataFile() : void {
    if ($this->confirm("Differences Found in $this->curFileSlug, Would you Like To Choose a 'Correct' File?")) {
      switch ($this->getRepoCorrectionChoiceValue()) {
        case 'c':
          $this->setCentralRepoVersionAsCanonical();
          $this->say('Deferring remote push until all of this repository\'s files have been processed.');
          break;

        case 'l':
          $this->setLeanRepoVersionAsCanonical();
          $this->say('Deferring remote push until all repositories have been processed.');
          break;

        default:
          $this->say('Skipping correction...');
      }
    }
  }

  /**
   * Gets the user's choice regarding which repository to treat as canonical.
   *
   * @return string
   *   The value of the choice.
   */
  protected function getRepoCorrectionChoiceValue() : string {
    $choice = '';
    while (!self::isValidCorrectionChoice($choice)) {
      $choice = strtolower($this->ask("What version is correct? Enter 'c' for central [+] or 'l' for lean [-] ('s' to skip)"));
      if (!self::isValidCorrectionChoice($choice)) {
        $this->io()->warning("Please choose either 'c' or 'l'");
      }
    }
    return $choice;
  }

  /**
   * Determines if a value is a valid correction choice.
   *
   * @param string $value
   *   The value to audit.
   *
   * @return bool
   *   TRUE if the value is valid, FALSE otherwise.
   */
  private static function isValidCorrectionChoice(string $value) : bool {
    return $value == 'c' || $value == 'l' || $value == 's';
  }

  /**
   * Writes and commits to the lean repo the central version of the file.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  protected function setCentralRepoVersionAsCanonical() : void {
    $output_contents = str_replace(
      $this->curDockerImage . ':' . $this->curDeployEnv,
      self::LEAN_REPO_IMAGE_PLACEHOLDER,
      $this->curCentralMetadataFileContents
    );
    file_put_contents($this->curLeanMetadataFile, $output_contents);
    $commit_message = "Update $this->curDeployEnv $this->curMetadataType from central repository";
    $this->curLeanRepoClone->repo->execute(
      [
        'add',
        str_replace($this->curLeanRepoClone->getTmpDir() . '/', '', $this->curLeanMetadataFile),
      ]
    );
    $this->curLeanRepoClone->repo->execute(
      [
        'commit',
        '--no-gpg-sign',
        '-m',
        $commit_message,
      ]
    );

    $this->curLeanRepoNeedsPush = TRUE;
  }

  /**
   * Writes and commits to the central repo the lean version of the file.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  protected function setLeanRepoVersionAsCanonical() : void {
    $output_contents = str_replace(self::LEAN_REPO_IMAGE_PLACEHOLDER, $this->curDockerImage . ':' . $this->curDeployEnv, $this->curLeanMetadataFileContents);
    file_put_contents($this->curCentralMetadataFile, $output_contents);
    $commit_message = "Update {$this->curLeanRepo['name']}/$this->curDeployEnv $this->curMetadataType from lean repository";
    $this->centralMetadataRepo->repo->execute(
      [
        'add',
        str_replace($this->centralMetadataRepo->getTmpDir() . '/', '', $this->curCentralMetadataFile),
      ]
    );
    $this->centralMetadataRepo->repo->execute(
      [
        'commit',
        '--no-gpg-sign',
        '-m',
        $commit_message,
      ]
    );
    $this->centralMetadataRepoNeedsPush = TRUE;
  }

  /**
   * Pushes the current lean metadata repository to GitHub.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  protected function pushCurLeanRepo() : void {
    $this->curLeanRepoClone->repo->execute(
      [
        'push',
        'origin',
        self::LEAN_REPO_BRANCH,
      ]
    );
  }

  /**
   * Pushes the central metadata repo to GitHub.
   *
   * @throws \CzProject\GitPhp\GitException
   */
  protected function pushCentralRepo() : void {
    $this->centralMetadataRepo->repo->execute(
      [
        'push',
        'origin',
        $this->options['central-repo-branch'],
      ]
    );
  }

}
