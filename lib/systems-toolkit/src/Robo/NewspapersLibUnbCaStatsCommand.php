<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use DateTimeInterface;
use Robo\Symfony\ConsoleIO;
use UnbLibraries\SystemsToolkit\KubeExecTrait;
use UnbLibraries\SystemsToolkit\NbhpSnsMessageTrait;
use UnbLibraries\SystemsToolkit\Robo\BasicKubeCommand;

/**
 * Class for NewspapersLibUnbCaStatsCommand Robo commands.
 */
class NewspapersLibUnbCaStatsCommand extends BasicKubeCommand {

  use NbhpSnsMessageTrait;
  use KubeExecTrait;

  public const NEWSPAPERS_FULL_URI = 'newspapers.lib.unb.ca';
  public const NEWSPAPERS_NAMESPACE = 'prod';
  public const TIME_STRING_FORMAT = DateTimeInterface::ISO8601;

  /**
   * Displays stats regarding newspapers.lib.unb.ca's digital content.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:stats
   *
   * @nbhp
   */
  public function getNewspapersStats(ConsoleIO $io) {
    $this->setIo($io);
    $this->setCurKubePodsFromSelector(['uri=' . self::NEWSPAPERS_FULL_URI], [self::NEWSPAPERS_NAMESPACE]);

    foreach ($this->kubeCurPods as $pod) {
      $this->say(sprintf('Querying statistics from %s', $pod->metadata->name));
      $pages = $this->getDrushQueryOutput($pod, 'SELECT count(*) FROM digital_serial_page');
      $issues = $this->getDrushQueryOutput($pod, 'SELECT count(*) FROM digital_serial_issue');
      $titles = $this->getDrushQueryOutput($pod, 'SELECT count(*) FROM digital_serial_title');

      $message = sprintf(
        "newspapers.lib.unb.ca - %s\n%s digital titles\n%s digital issues\n%s total pages",
        date(self::TIME_STRING_FORMAT),
        number_format($titles),
        number_format($issues),
        number_format($pages)
      );

      $this->setSendSnsMessage($message);
      $this->syskitIo->block($message);
    }
  }

  /**
   * Gets the output from a DRUSH sql query.
   *
   * @param object $pod
   *   The pod to query.
   * @param string $query
   *   The query to use.
   *
   * @return string
   *   The output from the drush command.
   */
  private function getDrushQueryOutput(object $pod, string $query) {
    $command_string = trim(
      sprintf(
        "%s '--kubeconfig=%s' '--namespace=%s' exec %s -- drush sqlq '%s'",
        $this->kubeBin,
        $this->kubeConfig,
        $pod->metadata->namespace,
        $pod->metadata->name,
        $query
      )
    );

    exec($command_string, $output, $return);
    return($output[0]);
  }

}
