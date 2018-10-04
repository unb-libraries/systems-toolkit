<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;

/**
 * Trait for interacting with AWS services.
 */
trait AwsCommandTrait {

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
   * Get the AWS key ID from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setAwsAccessKeyId() {
    $this->accessKeyId = Robo::Config()->get('syskit.aws.keyId');
    if (empty($this->accessKeyId)) {
      throw new \Exception(sprintf('The AWS Access Key ID is unset in %s.', $this->configFile));
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
      throw new \Exception(sprintf('The AWS Default Region is unset in %s.', $this->configFile));
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
      throw new \Exception(sprintf('The AWS Secret Access Key is unset in %s.', $this->configFile));
    }
  }

}
