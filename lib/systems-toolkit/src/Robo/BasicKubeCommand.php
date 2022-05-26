<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Symfony\ConsoleIO;
use UnbLibraries\SystemsToolkit\KubeExecTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for BasicKubeCommand Robo commands.
 */
class BasicKubeCommand extends SystemsToolkitCommand {

  use KubeExecTrait;

  /**
   * Retrieves a k8s service's logs from the current running container.
   *
   * @param string $uri
   *   The URI of the desired service. (pmportal.org)
   * @param string $namespace
   *   The namespace to retrieve the logs from. (dev)
   *
   * @throws \Exception
   *
   * @command k8s:logs
   * @usage pmportal.org dev
   */
  public function getKubeServiceLogsFromUri(
    ConsoleIO $io,
    string $uri,
    string $namespace
  ) {
    $this->setIo($io);
    $this->setCurKubePodsFromSelector(["uri=$uri"], [$namespace]);

    foreach ($this->kubeCurPods as $pod) {
      $this->syskitIo->say(sprintf('Listing Logs from %s:', $pod->metadata->name));
      $this->taskExec($this->kubeBin)
        ->arg("--kubeconfig=$this->kubeConfig")
        ->arg("--namespace={$pod->metadata->namespace}")
        ->arg('logs')
        ->arg($pod->metadata->name)
        ->run();
    }
  }

  /**
   * Opens a shell within a k8s service's currently running container.
   *
   * @param string $uri
   *   The URI of the desired service. (pmportal.org)
   * @param string $namespace
   *   The namespace to open the shell into. (dev)
   * @param string $shell
   *   The shell to use within the container.
   *
   * @throws \Exception
   *
   * @command k8s:shell
   */
  public function getKubeShellFromUri(
    ConsoleIO $io,
    string $uri,
    string $namespace,
    string $shell = 'sh'
  ) {
    $this->setIo($io);
    $this->setCurKubePodsFromSelector(["uri=$uri"], [$namespace]);
    $this->kubeExecAll($shell);
  }

}
