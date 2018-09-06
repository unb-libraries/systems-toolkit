<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Contract\CommandInterface;

/**
 * Trait for running a stack of Robo commands in parallel.
 */
trait QueuedParallelExecTrait
{

  /**
   * The queue of operations.
   *
   * @var \Robo\Contract\CommandInterface[]
   */
  private $queue = [];

  /**
   * The number of threads to employ when executing the commands.
   *
   * @var int
   */
  private $threads = NULL;

  /**
   * Add a command to the process queue.
   *
   * @param \Robo\Contract\CommandInterface $command
   *   The command to add.
   */
  public function setAddCommandToQueue(CommandInterface $command) {
    $this->queue[] = $command;
  }

  /**
   * Run the commands in the queue.
   *
   * @param string $operation_name
   *   The name of the operation to print.
   */
  public function setRunProcessQueue($operation_name = 'operation') {
    // Make sure the queue is populated.
    if (empty($this->queue)) {
      return;
    }

    // Set the threads if unset.
    if (empty($this->threads)) {
      $this->setThreadsDefault();
    }

    $items_to_run = count($this->queue);
    $this->say("Running $operation_name on $items_to_run files in batches of {$this->threads} parallel threads.");
    $item_counter = 0;

    while (!empty($this->queue)) {
      $parallel_stack = $this->taskParallelExec();
      for ($i=0; $i < $this->threads; $i++) {
        if (!empty($this->queue)) {
          $parallel_stack->process(array_shift($this->queue));
          $item_counter++;
        }
      }
      $parallel_stack->run();
      $queue_size = count($this->queue);
      $this->say("$item_counter items processed, $queue_size remain.");
    }
  }

  /**
   * Set the number of threads to employ when executing the commands.
   *
   * @param int $threads
   *   The number of threads to employ when executing the commands.
   */
  public function setThreads($threads) {
    $this->threads = $threads;
  }

  /**
   * Based on CPU, Guess at the number of threads to use to run these commands.
   */
  private function setThreadsDefault() {
    $command = 'grep "^cpu\scores" /proc/cpuinfo | uniq | awk \'{print $4}\'';
    $cores_to_use = floatval(shell_exec($command)) * 0.8;
    $this->threads = (int) floor($cores_to_use);
  }

}
