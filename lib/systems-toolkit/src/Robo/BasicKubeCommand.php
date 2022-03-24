<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\KubeExecTrait;
use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;

/**
 * Class for BasicKubeCommand Robo commands.
 */
class BasicKubeCommand extends SystemsToolkitCommand {

  use KubeExecTrait;

  /**
   * Gets a kubernetes service logs from the URI and namespace.
   *
   * @param string $uri
   *   The URI to the desired service. (pmportal.org)
   * @param string $namespace
   *   The namespace of the desired service. (dev)
   *
   * @throws \Exception
   *
   * @command k8s:logs
   * @usage pmportal.org dev
   */
  public function getKubeServiceLogsFromUri(string $uri, string $namespace) {
    $this->setCurKubePodsFromSelector(["uri=$uri"], [$namespace]);

    foreach ($this->kubeCurPods as $pod) {
      $this->say(sprintf('Listing Logs from %s:', $pod->metadata->name));
      $this->taskExec($this->kubeBin)
        ->arg("--kubeconfig=$this->kubeConfig")
        ->arg("--namespace={$pod->metadata->namespace}")
        ->arg('logs')
        ->arg($pod->metadata->name)
        ->run();
    }
  }

  /**
   * Gets a kubernetes service shell from a URI and namespace.
   *
   * @param string $uri
   *   The URI to the desired service. (pmportal.org)
   * @param string $namespace
   *   The namespace of the desired service. (dev)
   * @param string $shell
   *   The shell to use.
   *
   * @throws \Exception
   *
   * @command k8s:shell
   */
  public function getKubeShellFromUri(string $uri, string $namespace, string $shell = 'sh') {
    $this->setCurKubePodsFromSelector(["uri=$uri"], [$namespace]);
    $this->kubeExecAll($shell);
  }

}
