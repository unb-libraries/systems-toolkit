<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use Robo\Robo;
use UnbLibraries\SystemsToolkit\Robo\GuzzleClientTrait;

/**
 * Trait for interacting with a drupal instance with enabled REST.
 */
trait DrupalInstanceRestTrait {

  use GuzzleClientTrait;

  /**
   * The uri to the drupal instance.
   *
   * @var string
   */
  protected $drupalRestUri;

  /**
   * The drupal password to leverage for the REST API.
   *
   * @var string
   */
  protected $drupalRestPassword;

  /**
   * The drupal user to leverage for the REST API.
   *
   * @var string
   */
  protected $drupalRestUser;

  /**
   * The drupal user to leverage for the REST API.
   *
   * @var string
   */
  protected $drupalRestToken;

  /**
   * The drupal user to leverage for the REST API.
   *
   * @var string
   */
  protected $drupalRestResponse;

  /**
   * Set the drupal password from config.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setDrupalRestPassword() {
    $this->drupalRestPassword = Robo::Config()->get('syskit.drupal.rest.password');
    if (empty($this->drupalRestPassword)) {
      throw new \Exception(sprintf('The Drupal password is unset in %s.', $this->configFile));
    }
  }

  /**
   * Set the drupal user from config.
   *
   * @throws \Exception
   *
   * @hook post-init
   */
  public function setDrupalRestUser() {
    $this->drupalRestUser = Robo::Config()->get('syskit.drupal.rest.user');
    if (empty($this->drupalRestUser)) {
      throw new \Exception(sprintf('The Drupal user is unset in %s.', $this->configFile));
    }
  }

  /**
   * Set the drupal rest client token for a URI.
   *
   * @throws \Exception
   */
  protected function setUpDrupalRestClientToken() {
    $response = $this->guzzleClient->get($this->drupalRestUri . "/rest/session/token");
    $this->drupalRestToken =  (string) ($response->getBody());
  }

  /**
   * Get a entity from the Drupal Rest client.
   *
   * @param string $entity_uri
   *   The entity URI.
   *
   * @throws \Exception
   *
   * @return object
   *   The JSON object returned from the server.
   */
  protected function getDrupalRestEntity($entity_uri) {
    $this->setUpDrupalRestClientToken();
    $get_uri = $this->drupalRestUri . "$entity_uri?_format=json";
    $this->say($get_uri);
    $this->drupalRestResponse = $this->guzzleClient->get(
      $get_uri,
      [
        'auth' => [$this->drupalRestUser, $this->drupalRestPassword],
      ]
    );
    return json_decode((string) $this->drupalRestResponse->getBody());
  }

  /**
   * Patch an entity via the Drupal REST client.
   *
   * @param string $patch_uri
   *   The patch URI.
   * @param string $patch_content
   *   The patch content.
   *
   * @throws \Exception
   *
   * @return object
   *   The JSON object returned from the server.
   */
  protected function patchDrupalRestEntity($patch_uri, $patch_content) {
    $this->setUpDrupalRestClientToken();
    $patch_uri = $this->drupalRestUri . "$patch_uri?_format=json";
    $this->say($patch_uri);
    $this->drupalRestResponse = $this->guzzleClient
      ->patch($patch_uri, [
        'auth' => [$this->drupalRestUser, $this->drupalRestPassword],
        'body' => $patch_content,
        'headers' => [
          'Content-Type' => 'application/json',
          'X-CSRF-Token' => $this->drupalRestToken
        ],
      ]);
    return json_decode((string) $this->drupalRestResponse->getBody());
  }

}
