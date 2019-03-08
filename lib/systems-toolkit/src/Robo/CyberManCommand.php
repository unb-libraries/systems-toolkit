<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Aws\Credentials\Credentials;
use Aws\Sns\SnsClient;
use Robo\Robo;
use UnbLibraries\SystemsToolkit\Robo\AwsCommandTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for CyberMan Robo commands.
 */
class CyberManCommand extends SystemsToolkitCommand {

  use AwsCommandTrait;

  const ERROR_SNS_TOPIC_ID_UNSET = 'The Cyberman SNS topic ID is unset in %s.';

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
   * This hook will fire for all commands in this command file.
   *
   * @hook post-init
   */
  public function setSnsClient() {
    $credentials = new Credentials($this->accessKeyId, $this->secretAccessKey);
    $this->snsClient = new SnsClient([
        'version'     => 'latest',
        'region'      => $this->awsDefaultRegion,
        'credentials' => $credentials
    ]);
  }

  /**
   * Get the SNS Topic ID.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setSnsTopicId() {
    $this->snsTopicId = Robo::Config()->get('syskit.cyberman.awsSnsTopicId');
    if (empty($this->snsTopicId)) {
      throw new \Exception(sprintf(self::ERROR_SNS_TOPIC_ID_UNSET, $this->configFile));
    }
  }

  /**
   * Send a message via the CyberMan Slack bot.
   *
   * This command will send a message to the systems slack bot 'Cyberman'.
   *
   * @param string $message
   *   The message to send. Quote it!
   *
   * @throws \Exception
   *
   * @usage "Hello, cyberman here!"
   *
   * @command cyberman:sendmessage
   */
  public function sendMessage($message) {
    $this->say(
      $this->snsClient->publish(
        [
          'Message' => $message,
          'TopicArn' => $this->snsTopicId,
        ]
      )
    );
  }

}
