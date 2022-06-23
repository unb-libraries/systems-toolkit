<?php

namespace UnbLibraries\SystemsToolkit;

use Robo\Robo;
use UnbLibraries\SystemsToolkit\AwsSnsMessageTrait;

/**
 * Class for easy sending of SNS messages to NBHP channel.
 */
trait NbhpSnsMessageTrait {

  use AwsSnsMessageTrait;

  /**
   * Sets the NBHP SNS Topic ID.
   *
   * @throws \Exception
   *
   * @hook post-init @nbhp
   */
  public function setNbhpSnsTopicId() : void {
    $topic_id = Robo::Config()->get('syskit.nbhp.awsSnsTopicId');
    if (empty($topic_id)) {
      throw new \Exception('The NBHP SNS topic ID is unset in in config (syskit.nbhp.awsSnsTopicId).');
    }
    else {
      $this->setSnsTopicId($topic_id);
    }
  }

}
