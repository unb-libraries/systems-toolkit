<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use UnbLibraries\SystemsToolkit\Robo\AwsSnsMessageTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for CyberMan Robo commands.
 */
class CyberManCommand extends SystemsToolkitCommand {

  use AwsSnsMessageTrait;

  const ERROR_SNS_TOPIC_ID_UNSET = 'The Cyberman SNS topic ID is unset in %s.';

  /**
   * Set the Cyberman SNS Topic ID.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setCybermanSnsTopicId() {
    $topic_id = Robo::Config()->get('syskit.cyberman.awsSnsTopicId');
    if (empty($topic_id)) {
      throw new \Exception(sprintf(self::ERROR_SNS_TOPIC_ID_UNSET, $this->configFile));
    }
    else {
      $this->setSnsTopicId($topic_id);
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
  public function sendCybermanMessage($message) {
    $this->say(
      $this->setSendMessage($message)
    );
  }

}
