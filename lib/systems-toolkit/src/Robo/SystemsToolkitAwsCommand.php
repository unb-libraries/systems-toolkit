<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use Robo\Robo;

/**
 * Base class for SystemsToolkitAwsCommand Robo commands.
 */
class SystemsToolkitAwsCommand extends SystemsToolkitCommand {

  const ERROR_ACCESS_KEY_ID_UNSET = 'The AWS Access Key ID is unset in %s.';
  const ERROR_DEFAULT_REGION_UNSET = 'The AWS Default Region is unset in %s.';
  const ERROR_SECRET_ACCESS_KEY_UNSET = 'The AWS Secret Access Key is unset in %s.';

  /**
   * The AWS access key ID for the API.
   *
   * @var string
   */
  protected $accessKeyId;

  /**
   * The AWS secret access key for the API.
   *
   * @var string
   */
  protected $secretAccessKey;

  /**
   * The AWS region for the API.
   *
   * @var string
   */
  protected $awsDefaultRegion;

  /**
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   */
  public function initialize() {

    $this->setAwsAccessKeyId();
    $this->setAwsSecretAccessKey();
    $this->setAwsDefaultRegion();
  }

  /**
   * Get the AWS key ID from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setAwsAccessKeyId() {
    $this->accessKeyId = Robo::Config()->get('syskit.aws.keyId');
    if (empty($this->accessKeyId)) {
      throw new \Exception(sprintf(self::ERROR_ACCESS_KEY_ID_UNSET, $this->configFile));
    }
  }

  /**
   * Get the AWS default region from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setAwsDefaultRegion() {
    $this->awsDefaultRegion = Robo::Config()->get('syskit.aws.defaultRegion');
    if (empty($this->awsDefaultRegion)) {
      throw new \Exception(sprintf(self::ERROR_DEFAULT_REGION_UNSET, $this->configFile));
    }
  }

  /**
   * Get the AWS secret access key from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setAwsSecretAccessKey() {
    $this->secretAccessKey = Robo::Config()->get('syskit.aws.secretKey');
    if (empty($this->secretAccessKey)) {
      throw new \Exception(sprintf(self::ERROR_SECRET_ACCESS_KEY_UNSET, $this->configFile));
    }
  }

}
