<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use UnbLibraries\SystemsToolkit\Robo\AwsSnsMessageTrait;

/**
 * Class for easy sending of SNS messages to nbnp channel.
 */
trait NbhpSnsMessageTrait {

  use AwsSnsMessageTrait;

  /**
   * Set the NBHP SNS Topic ID.
   *
   * @throws \Exception
   *
   * @hook post-init @nbhp
   */
  public function setNbhpSnsTopicId() {
    $topic_id = Robo::Config()->get('syskit.nbhp.awsSnsTopicId');
    if (empty($topic_id)) {
      throw new \Exception(sprintf('The NBHP SNS topic ID is unset in in config (syskit.nbhp.awsSnsTopicId).'));
    }
    else {
      $this->setSnsTopicId($topic_id);
    }
  }

}
