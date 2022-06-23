<?php

namespace UnbLibraries\SystemsToolkit;

use Robo\ResultData;
use Robo\Robo;

/**
 * Class for SystemsToolkitKubeCommand commands.
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
   * Gets kubectl binary path from config.
   *
   * @throws \Exception
   *
   * @hook pre-init
   */
  public function setKubeBin() : void {
    $this->kubeBin = Robo::Config()->get('syskit.kubectl.bin');
    if (empty($this->kubeBin)) {
      throw new \Exception(sprintf('The kubectl binary path is unset in %s', $this->configFile));
    }
  }

  /**
   * Gets if the kubectl binary defined in the config file can be executed.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setKubeBinExists() : void {
    if (!is_executable($this->kubeBin)) {
      throw new \Exception(sprintf('The kubectl binary, %s, cannot be executed.', $this->kubeBin));
    }
  }

  /**
   * Gets kubectl config path from config.
   *
   * @throws \Exception
   *
   * @hook init
   */
  public function setKubeConfig() : void {
    $this->kubeConfig = Robo::Config()->get('syskit.kubectl.config');
    if (empty($this->kubeConfig)) {
      throw new \Exception(sprintf('The kubectl config location is unset in %s', $this->configFile));
    }
  }

  /**
   * Executes a command in all queued pods.
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
  private function kubeExecAll(
    string $exec,
    string $flags = '-it',
    array $args = [],
    bool $print_output = TRUE
  ) : void {
    foreach ($this->kubeCurPods as $pod) {
      $this->kubeExecPod($pod, $exec, $flags, $args, $print_output);
    }
  }

  /**
   * Executes a command in a single pod.
   *
   * @param object $pod
   *   The pod to execute the command in.
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
  private function kubeExecPod(
    object $pod,
    string $exec,
    string $flags = '-it',
    array $args = [],
    bool $print_output = TRUE
  ) : ResultData {
    $kube = $this->taskExec($this->kubeBin)
      ->printOutput($print_output)
      ->arg("--kubeconfig={$this->kubeConfig}")
      ->arg("--namespace={$pod->metadata->namespace}")
      ->arg('exec')
      ->arg($flags)
      ->arg($pod->metadata->name)
      ->arg('--')
      ->arg($exec);

    if (!empty($args)) {
      foreach ($args as $arg) {
        $kube->arg($arg);
      }
    }
    $this->say(sprintf('Executing %s in %s...', $exec, $pod->metadata->name));
    return $kube->run();
  }

  /**
   * Sets the current pods from a selector.
   *
   * @param string[] $selectors
   *   An array of selectors to filter pods against.
   * @param string[] $namespaces
   *   An array of namespaces to filter pods against.
   * @param bool $quiet
   *   TRUE if the output should be oppressed.
   * @param bool $only_running
   *   TRUE if the selector should only return running pods.
   *
   * @throws \Exception
   */
  protected function setCurKubePodsFromSelector(
    array $selectors,
    array $namespaces = ['dev', 'prod'],
    bool $quiet = FALSE,
    bool $only_running = TRUE
  ) : void {
    $selector_string = implode(',', $selectors);
    foreach ($namespaces as $namespace) {
      $command = "{$this->kubeBin} --kubeconfig={$this->kubeConfig} get pods --namespace=$namespace --selector=$selector_string -ojson";
      $output = shell_exec($command);
      if (!$quiet) {
        $this->say(sprintf('Getting pods from the cluster [%s, namespace=%s]', $selector_string, $namespace));
      }
      $this->setAddCurPodsFromJson($output, $only_running);
    }
  }

  /**
   * Adds pods to the current list from a JSON response string.
   *
   * @param string $json
   *   The JSON string to parse and add pods from.
   * @param bool $only_running
   *   TRUE if the selector should only return running pods.
   *
   * @throws \Exception
   */
  private function setAddCurPodsFromJson(
    string $json,
    bool $only_running = TRUE
  ) : void {
    $response = json_decode(
      $json,
      NULL,
      512,
      JSON_THROW_ON_ERROR
    );
    $items = $response->items;
    if ($only_running) {
      foreach ($items as $item_key => $item) {
        if ($item->status->phase != 'Running') {
          unset($items[$item_key]);
        }
      }
    }
    if (!empty($response->items)) {
      $this->kubeCurPods = array_merge($this->kubeCurPods, $items);
    }
    else {
      $this->say('Warning : No pods were returned from the cluster');
    }
  }

}
