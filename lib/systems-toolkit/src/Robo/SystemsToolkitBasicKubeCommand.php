<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use UnbLibraries\SystemsToolkit\Robo\KubeExecTrait;

/**
 * Base class for SystemsToolkitBasicKubeCommand.
 */
class SystemsToolkitBasicKubeCommand extends SystemsToolkitCommand {

  use KubeExecTrait;

  /**
   * Get a kubernetes service logs from the URI and namespace.
   *
   * @param string $uri
   *   The URI to the desired service. (dev-pmportal.org)
   * @param string $namespace
   *   The namespace of the desired service. (dev)
   *
   * @throws \Exception
   *
   * @command k8s:logs
   */
  public function getKubeServiceLogsFromUri($uri, $namespace) {
    $this->kubeCurNameSpace = $namespace;
    $this->setCurKubePodNamesFromUri($uri);

    foreach ($this->kubeCurPodNames as $pod_name) {
      $this->say(sprintf('Listing Logs from %s:', $pod_name));
      $pod_id = str_replace('pod/', '', $pod_name);
      $this->taskExec($this->kubeBin)
        ->arg("--kubeconfig={$this->kubeConfig}")
        ->arg("--namespace={$this->kubeCurNameSpace}")
        ->arg('logs')
        ->arg($pod_id)
        ->run();
    }
  }

  /**
   * Get a kubernetes service shell from the URI and namespace.
   *
   * @param string $uri
   *   The URI to the desired service. (dev-pmportal.org)
   * @param string $namespace
   *   The namespace of the desired service. (dev)
   * @param string $shell
   *   The shell to use.
   *
   * @throws \Exception
   *
   * @command k8s:shell
   */
  public function getKubeShellFromUri($uri, $namespace, $shell = 'sh') {
    $this->kubeCurNameSpace = $namespace;
    $this->setCurKubePodNamesFromUri($uri);

    foreach ($this->kubeCurPodNames as $pod_name) {
      $this->say(sprintf('Opening Shell for %s:', $pod_name));
      $pod_id = str_replace('pod/', '', $pod_name);
      $this->getKubeExec($pod_id, $shell);
    }
  }

}
