<?php

namespace UnbLibraries\SystemsToolkit;

use GuzzleHttp\Client;

/**
 * Trait for interacting with a drupal instance with enabled REST.
 */
trait GuzzleClientTrait {

  /**
   * The client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $guzzleClient;

  /**
   * Set the guzzle client.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function create() {
    $this->guzzleClient = new Client();
  }

}
