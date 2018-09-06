<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\Robo\SystemsToolkitCommand;
use Robo\Robo;

/**
 * Base class for SystemsToolkitKubeCommand Robo commands.
 */
class SystemsToolkitKubeCommand extends SystemsToolkitCommand {

  const ERROR_BINARY_PATH_UNSET = 'The kubectl binary path is unset in %s.';
  const ERROR_CONFIG_PATH = 'The kubectl config location is unset in %s.';
  const ERROR_KUBE_EXEC_ERROR = 'The kubectl command [%s] returned an error [%s]';
  const ERROR_KUBE_NO_PODS_ERROR = 'Warning : No pods were returned from the cluster [%s, %s]';
  const LABEL_LOGS_FOR_POD = 'Listing Logs from %s:';
  const LABEL_SHELL_FOR_POD = 'Opening Shell for %s:';

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
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   */
  public function initialize() {
    $this->setKubeBin();
    $this->setKubeConfig();
  }

  /**
   * Get kubectl binary path from config.
   *
   * @throws \Exception
   */
  private function setKubeBin() {
    $this->kubeBin = Robo::Config()->get('syskit.kubectl.bin');
    if (empty($this->kubeBin)) {
      throw new \Exception(sprintf(self::ERROR_BINARY_PATH_UNSET, $this->configFile));
    }
  }

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
      $this->say(sprintf(self::LABEL_LOGS_FOR_POD, $pod_name));
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
      $this->say(sprintf(self::LABEL_SHELL_FOR_POD, $pod_name));
      $pod_id = str_replace('pod/', '', $pod_name);
      $this->getKubeExec($pod_name, $shell);
    }
  }

  /**
   * Get kubectl binary path from config.
   *
   * @throws \Exception
   */
  private function setKubeConfig() {
    $this->kubeConfig = Robo::Config()->get('syskit.kubectl.config');
    if (empty($this->kubeConfig)) {
      throw new \Exception(sprintf(self::ERROR_CONFIG_PATH, $this->configFile));
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
      throw new \Exception(sprintf(self::ERROR_KUBE_EXEC_ERROR, $command, implode("\n", $output)));
    }
    elseif (empty($output)) {
      $this->say(sprintf(self::ERROR_KUBE_NO_PODS_ERROR, $uri, $this->kubeCurNameSpace));
    }
    else {
      $this->kubeCurPodNames = $output;
    }
  }

}
