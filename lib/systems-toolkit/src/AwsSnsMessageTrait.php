<?php

namespace UnbLibraries\SystemsToolkit;

use Aws\Credentials\Credentials;
use Aws\Result;
use Aws\Sns\SnsClient;
use UnbLibraries\SystemsToolkit\AwsCommandTrait;

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
  protected SnsClient $snsClient;

  /**
   * The SNS Topic ID for the Cyberman Queue.
   *
   * @var string
   */
  protected string $snsTopicId;

  /**
   * Set the SNS Client.
   *
   * @hook post-init
   */
  public function setSnsClient() : void {
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
  public function setSnsTopicId(string $topic_id) : void {
    $this->snsTopicId = $topic_id;
  }

  /**
   * Sends a message to SNS.
   *
   * @param string $message
   *   The message to send. Quote it!
   *
   * @throws \Exception
   *
   * @return \Aws\Result
   *   The result of the message send.
   */
  protected function setSendSnsMessage(string $message): Result {
    return $this->snsClient->publish(
      [
        'Message' => $message,
        'TopicArn' => $this->snsTopicId,
      ]
    );
  }

}
