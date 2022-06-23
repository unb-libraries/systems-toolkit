<?php

namespace UnbLibraries\SystemsToolkit;

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
  protected string $accessKeyId;

  /**
   * The AWS secret access key for the API.
   *
   * @var string
   */
  protected string $secretAccessKey;

  /**
   * The AWS region for the API.
   *
   * @var string
   */
  protected string $awsDefaultRegion;

  /**
   * Gets the AWS key ID from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setAwsAccessKeyId() : void {
    $this->accessKeyId = Robo::Config()->get('syskit.aws.keyId');
    if (empty($this->accessKeyId)) {
      throw new \Exception(sprintf('The AWS Access Key ID is unset in %s.', $this->configFile));
    }
  }

  /**
   * Gets the AWS default region from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setAwsDefaultRegion() : void {
    $this->awsDefaultRegion = Robo::Config()->get('syskit.aws.defaultRegion');
    if (empty($this->awsDefaultRegion)) {
      throw new \Exception(sprintf('The AWS Default Region is unset in %s.', $this->configFile));
    }
  }

  /**
   * Gets the AWS secret access key from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setAwsSecretAccessKey() : void {
    $this->secretAccessKey = Robo::Config()->get('syskit.aws.secretKey');
    if (empty($this->secretAccessKey)) {
      throw new \Exception(sprintf('The AWS Secret Access Key is unset in %s.', $this->configFile));
    }
  }

}
