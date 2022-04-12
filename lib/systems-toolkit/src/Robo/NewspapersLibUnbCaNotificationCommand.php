<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use UnbLibraries\SystemsToolkit\AwsSnsMessageTrait;

/**
 * Class for NewspapersLibUnbCaNotificationCommand Robo commands.
 */
class NewspapersLibUnbCaNotificationCommand extends BasicKubeCommand {

  public const TIME_STRING_FORMAT = \DateTimeInterface::ISO8601;

  use AwsSnsMessageTrait;

  /**
   * Sets the NBHP SNS Topic ID.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setNbhpNotificationSnsTopicId() {
    $topic_id = Robo::Config()->get('syskit.nbhp.awsNotificationTopicId');
    if (empty($topic_id)) {
      throw new \Exception('The NBHP SNS notification topic ID is unset in in config (syskit.nbhp.awsNotificationTopicId).');
    }
    else {
      $this->setSnsTopicId($topic_id);
    }
  }

  /**
   * Sends an import status message to the NBHP notification list.
   *
   * @param int $title_id
   *   The issue's remote entity ID.
   * @param string $year
   *   The issue's remote page_no.
   * @param string $result
   *   The import result.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:import:notify
   *
   * @nbhp
   */
  public function getNewspaperIngestStatusNotification($title_id, $year, $result = 'complete') {
    $message = sprintf(
      "newspapers.lib.unb.ca - %s\n[%s:%s] import (%s)",
      date(self::TIME_STRING_FORMAT),
      number_format((float) $title_id),
      number_format((float) $year),
      $result
    );
    $this->setSendSnsMessage($message);
  }

}
