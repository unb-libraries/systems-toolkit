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
   * The pod objects to exec commands in.
   *
   * @var object[]
   */
  protected $kubeCurPods = [];

  /**
   * Get kubectl binary path from config.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function setKubeBin() {
    $this->kubeBin = Robo::Config()->get('syskit.kubectl.bin');
    if (empty($this->kubeBin)) {
      throw new \Exception(sprintf('The kubectl binary path is unset in %s', $this->configFile));
    }
  }

  /**
   * Get if the kubectl binary defined in the config file can be executed.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setKubeBinExists() {
    if (!is_executable($this->kubeBin)) {
      throw new \Exception(sprintf('The kubectl binary, %s, cannot be executed.', $this->kubeBin));
    }
  }

  /**
   * Get kubectl config path from config.
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
   */
  private function kubeExecAll($exec, $flags = '-it', $args = [], $print_output = TRUE) {
    foreach ($this->kubeCurPods as $pod) {
      $this->kubeExecPod($pod, $exec, $flags, $args, $print_output);
    }
  }

  /**
   * Execute a command in a single pod.
   *
   * @param object $pod
   *   The command to execute (i.e. ls)
   * @param string $exec
   *   The command to execute (i.e. ls)
   * @param string $flags
   *   Flags to pass to kubectl exec.
   * @param string[] $args
   *   A list of arguments to pass to the in-container command (i.e. -al).
   * @param bool $print_output
   *   TRUE if the command should output results. False otherwise.
   *
   * @return \Robo\ResultData
   *   The result of the execution.
   */
  private function kubeExecPod($pod, $exec, $flags = '-it', $args = [], $print_output = TRUE) {
    $kube = $this->taskExec($this->kubeBin)
      ->printOutput($print_output)
      ->arg("--kubeconfig={$this->kubeConfig}")
      ->arg("--namespace={$pod->metadata->namespace}")
      ->arg('exec')
      ->arg($flags)
      ->arg($pod->metadata->name)
      ->arg($exec);

    if (!empty($args)) {
      $kube->arg('--');
      foreach ($args as $arg) {
        $kube->arg($arg);
      }
    }
    $this->say(sprintf('Executing %s in %s...', $exec, $pod->metadata->name));
    return $kube->run();
  }

  /**
   * Set the current pods from a selector.
   *
   * @param string[] $selectors
   *   An array of selectors to filter pods against.
   * @param string[] $namespaces
   *   An array of namespaces to filter pods against.
   *
   * @throws \Exception
   */
  private function setCurKubePodsFromSelector(array $selectors, array $namespaces = ['dev', 'prod']) {
    $selector_string = implode(',', $selectors);
    foreach ($namespaces as $namespace) {
      $command = "{$this->kubeBin} --kubeconfig={$this->kubeConfig} get pods --namespace=$namespace --selector=$selector_string -ojson";
      $output = shell_exec($command);
      $this->say(sprintf('Getting pods from the cluster [%s, namespace=%s]', $selector_string, $namespace));
      $this->setAddCurPodsFromJson($output);
    }
  }

  /**
   * Add pods to the current list from a JSON response string.
   *
   * @param string $json
   *   The JSON string to parse and add pods from.
   *
   * @throws \Exception
   */
  private function setAddCurPodsFromJson($json) {
    $response = json_decode($json);
    if (!empty($response->items)) {
      $this->kubeCurPods = array_merge($this->kubeCurPods, $response->items);
    }
    else {
      $this->say('Warning : No pods were returned from the cluster');
    }
  }

}
