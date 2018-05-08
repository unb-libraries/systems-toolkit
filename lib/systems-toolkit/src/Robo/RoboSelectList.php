<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * RoboSelectList commands.
 */
class RoboSelectList {

  /**
   * The columns to print.
   *
   * @var array
   */
  private $columns = [];

  /**
   * The default items.
   *
   * @var array
   */
  private $defaults = [];

  /**
   * The output interface to use.
   *
   * @var \Symfony\Component\Console\Output\OutputInterface
   */
  private $output;

  /**
   * The values.
   *
   * @var array
   */
  private $values = [];

  /**
   * Constructs a RoboSelectList object.
   *
   * @param array $values
   *   An array of associative arrays, each element containing the values to
   *   select.
   * @param array $columns
   *   An array of strings containing the $values array keys that the table
   *   should print.
   * @param array $defaults
   *   The default values to show as selected on the first iteration.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output interface to use.
   */
  private function __construct(array $values, array $columns, array $defaults, OutputInterface $output) {
    $this->values = $values;
    $this->columns = $columns;
    $this->defaults = $defaults;
    $this->output = $output;
  }

  /**
   * Serve a tableSelectList to console.
   *
   * @param array $values
   *   An array of associative arrays, each element containing the values to
   *   select.
   * @param array $columns
   *   An array of strings containing the $values array keys that the table
   *   should print.
   * @param array $defaults
   *   The default values to show as selected on the first iteration.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   The output interface to use.
   */
  public static function tableSelectList(array $values, array $columns, array $defaults, OutputInterface $output) {
    $obj = new static($values, $columns, $defaults, $output);
  }

}
