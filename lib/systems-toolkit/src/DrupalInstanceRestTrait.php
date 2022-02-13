<?php

namespace UnbLibraries\SystemsToolkit;

use GuzzleHttp\Exception\BadResponseException;
use Robo\Robo;
use UnbLibraries\SystemsToolkit\GuzzleClientTrait;

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
   * Get a entity from the Drupal REST client.
   *
   * @param string $entity_uri
   *   The entity URI.
   * @param bool $silent
   *
   * @throws \Exception
   *
   * @return object
   *   The JSON object returned from the server.
   */
  protected function getDrupalRestEntity($entity_uri, $silent = FALSE) {
    $uri = "$entity_uri?_format=json";
    $args = [];
    $method = 'get';
    return $this->getGuzzleRequest($uri, $method, $args, $silent);
  }

  /**
   * General Guzzle request initiator.
   *
   * @param $uri
   * @param $method
   * @param array $args
   * @param bool $silent
   * @param bool $retry_on_error
   * @param int $retry_counter
   * @param int $max_retries
   *
   * @return mixed
   * @throws \Exception
   */
  protected function getGuzzleRequest($uri, $method, $args = [], $silent = FALSE, $retry_on_error = TRUE, $retry_counter = 0, $max_retries = 5) {
    $this->setUpDrupalRestClientToken();
    $endpoint_uri = $this->drupalRestUri . $uri;
    try {
      if (!$silent) {
        $this->say($endpoint_uri);
      }
      $auth_args = [
        'auth' => [$this->drupalRestUser, $this->drupalRestPassword],
      ];
      $this->drupalRestResponse = $this->guzzleClient->$method(
        $endpoint_uri,
        array_merge($auth_args, $args)
      );
      return json_decode((string) $this->drupalRestResponse->getBody(), null, 512, JSON_THROW_ON_ERROR);
    } catch (BadResponseException) {
      $retry_counter++;
      if ($retry_on_error && $retry_counter < $max_retries) {
        return $this->getGuzzleRequest($uri, $method, $args, $silent, $retry_on_error, $retry_counter);
      }
    }
    throw new \Exception(sprintf('The REST request to %s failed.', $endpoint_uri));
  }

  /**
   * Set the Drupal REST client token for a URI.
   *
   * @throws \Exception
   */
  protected function setUpDrupalRestClientToken() {
    $response = $this->guzzleClient->get($this->drupalRestUri . "/session/token");
    $this->drupalRestToken = (string) ($response->getBody());
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
    $uri = "$patch_uri?_format=json";
    $args = [
      'body' => $patch_content,
      'headers' => [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $this->drupalRestToken
      ],
    ];
    $method = 'patch';
    return $this->getGuzzleRequest($uri, $method, $args);
  }

  /**
   * Create an entity via the Drupal REST client.
   *
   * @param string $create_uri
   *   The patch URI.
   * @param string $create_content
   *   The patch content.
   *
   * @throws \Exception
   *
   * @return object
   *   The JSON object returned from the server.
   */
  protected function createDrupalRestEntity($create_uri, $create_content) {
    $uri = "$create_uri?_format=json";
    $args = [
      'body' => $create_content,
      'headers' => [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $this->drupalRestToken
      ],
    ];
    $method = 'post';
    return $this->getGuzzleRequest($uri, $method, $args);
  }

  /**
   * Upload a file for an entity field using the Drupal REST client.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string $entity_bundle
   *   The entity bundle.
   * @param string $field_name
   *   The field name where the file will be attached.
   * @param string $file_contents
   *   The contents of the file to attach.
   * @param string $file_name
   *   The name to use for the file when attaching.
   *
   * @throws \Exception
   *
   * @return object
   *   The JSON object of the file returned from the server.
   */
  protected function uploadDrupalRestFileToEntityField($entity_type_id, $entity_bundle, $field_name, $file_contents, $file_name) {
    $uri = "/file/upload/{$entity_type_id}/{$entity_bundle}/{$field_name}?_format=json";
    $args = [
      'body' => $file_contents,
      'headers' => [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => "file; filename=\"$file_name\"",
        'X-CSRF-Token' => $this->drupalRestToken
      ],
    ];
    $method = 'post';
    return $this->getGuzzleRequest($uri, $method, $args);
  }

}
