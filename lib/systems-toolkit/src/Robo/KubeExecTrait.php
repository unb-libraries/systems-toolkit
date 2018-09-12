<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;

/**
 * Class for SystemsToolkitKubeCommand Robo commands.
 */
trait KubeExecTrait {

  /**
   * The path to the kubectl binary.
   *
   * @var string
   */
  protected $kubeBin;

  /**
   * The path to the kubeconfig to use.
   *
   * @var string
   */
  protected $kubeConfig;

  /**
   * The path to the kubeconfig to use.
   *
   * @var string
   */
  protected $kubeCurNameSpaces = NULL;

  /**
   * The path to the kubeconfig to use.
   *
   * @var string
   */
  protected $kubeCurPodNames = [];

  /**
   * Get kubectl binary path from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setKubeBin() {
    $this->kubeBin = Robo::Config()->get('syskit.kubectl.bin');
    if (empty($this->kubeBin)) {
      throw new \Exception(sprintf('The kubectl binary path is unset in %s', $this->configFile));
    }
  }

  /**
   * Execute a command in a kubernetes pod.
   *
   * @param string $pod_name
   *   The pod name.
   * @param string $exec
   *   The command to execute (ls -al)
   * @param string $flags
   *   Flags to pass to kubectl exec.
   *
   * @throws \Exception
   */
  private function getKubeExec($pod_name, $exec, $flags = '-it') {
    $pod_id = str_replace('pod/', '', $pod_name);
    $this->taskExec($this->kubeBin)
      ->interactive()
      ->arg("--kubeconfig={$this->kubeConfig}")
      ->arg("--namespace={$this->kubeCurNameSpace}")
      ->arg('exec')
      ->arg($flags)
      ->arg($pod_id)
      ->arg($exec)
      ->run();
  }

  /**
   * Get kubectl binary path from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setKubeConfig() {
    $this->kubeConfig = Robo::Config()->get('syskit.kubectl.config');
    if (empty($this->kubeConfig)) {
      throw new \Exception(sprintf('The kubectl config location is unset in %s', $this->configFile));
    }
  }

  /**
   * Set the current pod names to target from a uri metadata label.
   *
   * @throws \Exception
   */
  private function setCurKubePodNamesFromUri($uri) {
    $command = "{$this->kubeBin} --kubeconfig={$this->kubeConfig} get pods --namespace={$this->kubeCurNameSpace} --selector=uri=$uri -oname";
    exec($command, $output, $return);
    if ($return != 0) {
      throw new \Exception(sprintf('The kubectl command [%s] returned an error [%s]', $command, implode("\n", $output)));
    }
    elseif (empty($output)) {
      $this->say(sprintf('Warning : No pods were returned from the cluster [%s, %s]', $uri, $this->kubeCurNameSpace));
    }
    else {
      $this->kubeCurPodNames = $output;
    }
  }

}
