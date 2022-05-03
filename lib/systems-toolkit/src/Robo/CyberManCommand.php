<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use Robo\Symfony\ConsoleIO;
use UnbLibraries\SystemsToolkit\AwsSnsMessageTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for CyberMan Robo commands.
 */
class CyberManCommand extends SystemsToolkitCommand {

  use AwsSnsMessageTrait;

  public const ERROR_SNS_TOPIC_ID_UNSET = 'The CyberMan SNS topic ID is unset in %s.';

  /**
   * Sets the CyberMan SNS Topic ID.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setCyberManSnsTopicId() {
    $topic_id = Robo::Config()->get('syskit.cyberman.awsSnsTopicId');
    if (empty($topic_id)) {
      throw new \Exception(sprintf(self::ERROR_SNS_TOPIC_ID_UNSET, $this->configFile));
    }
    else {
      $this->setSnsTopicId($topic_id);
    }
  }

  /**
   * Sends a message via the CyberMan Slack bot.
   *
   * This command will send a message to the systems slack bot 'CyberMan'.
   *
   * @param string $message
   *   The message to send. Quote it!
   *
   * @throws \Exception
   *
   * @command cyberman:sendmessage
   * @usage "Hello, cyberman here!"
   */
  public function sendCyberManMessage(
    ConsoleIO $io,
    string $message
  ) {
    $this->setIo($io);
    $this->syskitIo->say(
      $this->setSendSnsMessage($message)
    );
  }

}
