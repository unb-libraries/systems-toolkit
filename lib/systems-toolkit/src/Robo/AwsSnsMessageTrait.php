<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Aws\Credentials\Credentials;
use Aws\Sns\SnsClient;

/**
 * Class for easy sending of SNS messages.
 */
trait AwsSnsMessageTrait {

  use AwsCommandTrait;

  /**
   * The AWS SNS Client.
   *
   * @var \Aws\Sns\SnsClient
   */
  protected $snsClient;

  /**
   * The SNS Topic ID for the Cyberman Queue.
   *
   * @var string
   */
  protected $snsTopicId;

  /**
   * Set the SNS Client.
   *
   * @hook post-init
   */
  public function setSnsClient() {
    $credentials = new Credentials($this->accessKeyId, $this->secretAccessKey);
    $this->snsClient = new SnsClient(
      [
        'version' => 'latest',
        'region' => $this->awsDefaultRegion,
        'credentials' => $credentials,
      ]
    );
  }

  /**
   * Sets the SNS topic ID.
   *
   * @param string $topic_id
   *   The SNS topic_id to target.
   *
   * @throws \Exception
   */
  public function setSnsTopicId(string $topic_id) {
    $this->snsTopicId = $topic_id;
  }

  /**
   * Sends a message to SNS.
   *
   * @param string $message
   *   The message to send. Quote it!
   *
   * @return \Aws\Result
   *
   * @throws \Exception
   */
  protected function setSendSnsMessage(string $message): \Aws\Result {
    return $this->snsClient->publish(
      [
        'Message' => $message,
        'TopicArn' => $this->snsTopicId,
      ]
    );
  }

}
