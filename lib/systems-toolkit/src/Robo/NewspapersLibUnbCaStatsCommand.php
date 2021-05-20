<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\BasicKubeCommand;

/**
 * Class for NewspapersLibUnbCaStatsCommand Robo commands.
 */
class NewspapersLibUnbCaStatsCommand extends BasicKubeCommand {

  use KubeExecTrait;

  const NEWSPAPERS_FULL_URI = 'newspapers.lib.unb.ca';
  const NEWSPAPERS_NAMESPACE = 'prod';
  const TIME_STRING_FORMAT = \DateTime::ISO8601;

  /**
   * Displays stats regarding newspapers.lib.unb.ca's digital content.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:stats
   */
  public function getNewspapersStats() {
    $this->setCurKubePodsFromSelector(['uri=' . self::NEWSPAPERS_FULL_URI], [self::NEWSPAPERS_NAMESPACE]);

    foreach ($this->kubeCurPods as $pod) {
      $this->say(sprintf('Querying statistics from %s', $pod->metadata->name));
      $pages = $this->getDrushQueryOutput($pod, 'SELECT count(*) FROM digital_serial_page');
      $issues = $this->getDrushQueryOutput($pod, 'SELECT count(*) FROM digital_serial_issue');
      $titles = $this->getDrushQueryOutput($pod, 'SELECT count(*) FROM digital_serial_title');

      $this->io()->block(
          sprintf(
          '[%s] newspapers.lib.unb.ca: %s digital titles | %s digital issues | %s scanned pages.',
          date(self::TIME_STRING_FORMAT),
          number_format($titles),
          number_format($issues),
          number_format($pages)
        )
      );
    }
  }

  /**
   * Gets the output from a DRUSH sql query.
   *
   * @param $pod
   *   The pod ID to query.
   * @param $query
   *   The query to use.
   *
   * @return string
   */
  private function getDrushQueryOutput($pod, $query) {
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
