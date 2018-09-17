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
  protected $kubeCurNameSpace = NULL;

  /**
   * The path to the kubeconfig to use.
   *
   * @var string[]
   */
  protected $kubeCurPods = [];

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
   * Execute a command in all queued pods.
   *
   * @param string $exec
   *   The command to execute (i.e. ls)
   * @param string $flags
   *   Flags to pass to kubectl exec.
   * @param string[] $args
   *   A list of arguments to pass to the in-container command (i.e. -al).
   * @param bool $print_output
   *   TRUE if the command should output results. False otherwise.
   *
   * @throws \Exception
   *
   * @return \Robo\Result
   *   The result of the command.
   */
  private function kubeExec($exec, $flags = '-it', $args = [], $print_output = TRUE) {
    foreach ($this->kubeCurPods as $pod_name) {
      $pod_id = str_replace('pod/', '', $pod_name);
      $kube = $this->taskExec($this->kubeBin)
        ->printOutput($print_output)
        ->arg("--kubeconfig={$this->kubeConfig}")
        ->arg("--namespace={$this->kubeCurNameSpace}")
        ->arg('exec')
        ->arg($flags)
        ->arg($pod_id)
        ->arg($exec);

      if (!empty($args)) {
        $kube->arg('--');
        foreach ($args as $arg) {
          $kube->arg($arg);
        }
      }

      return $kube->run();
    }
  }

  private function kubeExecPod($name, $namespace, $exec, $flags = '-it', $args = [], $print_output = TRUE) {
    $kube = $this->taskExec($this->kubeBin)
      ->printOutput($print_output)
      ->arg("--kubeconfig={$this->kubeConfig}")
      ->arg("--namespace=$namespace")
      ->arg('exec')
      ->arg($flags)
      ->arg($name)
      ->arg($exec);

    if (!empty($args)) {
      $kube->arg('--');
      foreach ($args as $arg) {
        $kube->arg($arg);
      }
    }

    return $kube->run();
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
   * Set the current pods from a selector.
   *
   * @throws \Exception
   */
  private function setCurKubePodsFromSelector(array $selector, array $namespaces = ['dev', 'prod']) {
    $selector_string = implode(',', $selector);
    foreach ($namespaces as $namespace) {
      $command = "{$this->kubeBin} --kubeconfig={$this->kubeConfig} get pods --namespace=$namespace --selector=$selector_string -ojson";
      $output = shell_exec($command);
      if (empty($output)) {
        $this->say(sprintf('Warning : Empty response from the cluster [%s=%s, %s]', $selector_string, $namespace));
      }
      else {
        $this->setAddCurPodsFromJson($output);
      }
    }
  }

  private function setAddCurPodsFromJson($output) {
    $response = json_decode($output);
    if (!empty($response->items)) {
      $this->kubeCurPods = array_merge($this->kubeCurPods, $response->items);
    }
    else {
      $this->say('Warning : No pods were returned from the cluster');
    }
  }

}
