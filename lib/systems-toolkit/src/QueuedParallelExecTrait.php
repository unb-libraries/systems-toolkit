<?php

namespace UnbLibraries\SystemsToolkit;

use Robo\Contract\CommandInterface;
use Symfony\Component\Process\Process;

/**
 * Trait for running a stack of Robo commands in parallel.
 */
trait QueuedParallelExecTrait {

  /**
   * The queue of operations.
   *
   * @var \Robo\Contract\CommandInterface[]
   */
  private array $queue = [];

  /**
   * The number of threads to employ when executing the commands.
   *
   * @var int
   */
  private int $threads;

  /**
   * Adds a command to the process queue.
   *
   * @param \Robo\Contract\CommandInterface $command
   *   The command to add.
   */
  public function setAddCommandToQueue(CommandInterface $command) : void {
    $this->queue[] = $command;
  }

  /**
   * Runs the commands in the queue.
   *
   * Uses a rolling pool: a new command starts as soon as any running one
   * finishes, rather than waiting for a whole batch to complete.
   *
   * @param string $operation_name
   *   The name of the operation to print.
   *
   * @return int
   *   The number of commands that failed.
   */
  public function setRunProcessQueue(
    string $operation_name = 'operation'
  ) : int {
    // Make sure the queue is populated.
    if (empty($this->queue)) {
      return 0;
    }

    // Set the threads if unset.
    if (empty($this->threads)) {
      $this->setThreadsDefault();
    }

    $items_to_run = count($this->queue);
    $this->say("Running $operation_name on $items_to_run files with {$this->threads} parallel threads.");
    $running = [];
    $finished = 0;
    $failures = 0;

    while (!empty($this->queue) || !empty($running)) {
      // Fill free slots.
      while (!empty($this->queue) && count($running) < $this->threads) {
        $command_string = array_shift($this->queue)->getCommand();
        $process = Process::fromShellCommandline($command_string, getcwd());
        $process->setTimeout(NULL);
        $process->start();
        $running[] = $process;
      }

      // Reap finished processes.
      foreach ($running as $idx => $process) {
        if ($process->isRunning()) {
          continue;
        }
        unset($running[$idx]);
        $finished++;
        if (!$process->isSuccessful()) {
          $failures++;
          $this->say(sprintf(
            "%s FAILED (exit %s): %s\n%s",
            $operation_name,
            $process->getExitCode(),
            $process->getCommandLine(),
            trim($process->getErrorOutput() ?: $process->getOutput())
          ));
        }
        if ($finished % $this->threads === 0 || $finished === $items_to_run) {
          $this->say(sprintf('%d items processed, %d remain.', $finished, $items_to_run - $finished));
        }
      }
      usleep(100000);
    }

    if ($failures) {
      $this->say("$failures of $items_to_run $operation_name commands failed.");
    }
    return $failures;
  }

  /**
   * Sets the number of threads to employ when executing the commands.
   *
   * @param int $threads
   *   The number of threads to employ when executing the commands.
   */
  public function setThreads(int $threads) : void {
    $this->threads = $threads;
  }

  /**
   * Based on CPU, guesses at the threads to use to run these commands.
   */
  private function setThreadsDefault() : void {
    $command = 'grep "^cpu\scores" /proc/cpuinfo | uniq | awk \'{print $4}\'';
    $cores_to_use = floatval(shell_exec($command)) * 0.8;
    // Never zero, which would leave the queue unprocessed.
    $this->threads = max(1, (int) floor($cores_to_use));
  }

}
