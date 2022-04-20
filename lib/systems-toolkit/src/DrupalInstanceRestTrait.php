<?php

namespace UnbLibraries\SystemsToolkit;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;
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
  protected string $drupalRestUri;

  /**
   * The drupal password to leverage for the REST API.
   *
   * @var string
   */
  protected string $drupalRestPassword;

  /**
   * The drupal user to leverage for the REST API.
   *
   * @var string
   */
  protected string $drupalRestUser;

  /**
   * The drupal user to leverage for the REST API.
   *
   * @var string
   */
  protected string $drupalRestToken;

  /**
   * The response from the Drupal REST request..
   *
   * @var \GuzzleHttp\Psr7\Response
   */
  protected Response $drupalRestResponse;

  /**
   * Sets the drupal password from config.
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
   * Sets the drupal user from config.
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
   * Gets an entity from the Drupal REST client.
   *
   * @param string $entity_uri
   *   The entity URI.
   * @param bool $silent
   *   TRUE if the query should not display any information.
   *
   * @throws \Exception
   *
   * @return object
   *   The JSON object returned from the server.
   */
  protected function getDrupalRestEntity(
    string $entity_uri,
    bool $silent = FALSE
  ) : object {
    $uri = "$entity_uri?_format=json";
    $args = [];
    $method = 'get';
    return $this->getGuzzleRequest($uri, $method, $args, $silent);
  }

  /**
   * Provides a general Guzzle request initiator.
   *
   * @param string $uri
   *   The URI to send the request to.
   * @param string $method
   *   The request method.
   * @param array $args
   *   Any arguments to pass to the request.
   * @param bool $silent
   *   TRUE if the query should not display any information.
   * @param bool $retry_on_error
   *   TRUE if the query should retry if an error occurs.
   * @param int $retry_counter
   *   The number of retries that have occurred so far.
   * @param int $max_retries
   *   The maximum number of retries.
   *
   * @return mixed
   *   The result from the request.
   *
   * @throws \Exception
   */
  protected function getGuzzleRequest(
    string $uri,
    string $method,
    array $args = [],
    bool $silent = FALSE,
    bool $retry_on_error = TRUE,
    int $retry_counter = 0,
    int $max_retries = 5
  ) : mixed {
    $this->checkSetUpDrupalRestClientToken();
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
      return json_decode(
        (string) $this->drupalRestResponse->getBody(),
        NULL,
        512,
        JSON_THROW_ON_ERROR
      );
    }
    catch (BadResponseException) {
      $retry_counter++;
      if ($retry_on_error && $retry_counter < $max_retries) {
        return $this->getGuzzleRequest($uri, $method, $args, $silent, $retry_on_error, $retry_counter);
      }
    }
    throw new \Exception(sprintf('The REST request to %s failed.', $endpoint_uri));
  }

  /**
   * Sets the Drupal REST client token for a URI.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function setUpDrupalRestClientToken() {
    $response = $this->guzzleClient->get($this->drupalRestUri . "/session/token");
    $this->drupalRestToken = (string) ($response->getBody());
  }

  /**
   * Sets the Drupal REST client token for a URI if it is unset.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function checkSetUpDrupalRestClientToken() {
    if (empty($this->drupalRestToken)) {
      $this->setUpDrupalRestClientToken();
    }
  }

  /**
   * Patches an entity via the Drupal REST client.
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
  protected function patchDrupalRestEntity(string $patch_uri, string $patch_content) : object {
    $this->checkSetUpDrupalRestClientToken();
    $uri = "$patch_uri?_format=json";
    $args = [
      'body' => $patch_content,
      'headers' => [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $this->drupalRestToken,
      ],
    ];
    $method = 'patch';
    return $this->getGuzzleRequest($uri, $method, $args);
  }

  /**
   * Creates an entity via the Drupal REST client.
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
  protected function createDrupalRestEntity(string $create_uri, string $create_content) : object {
    $this->checkSetUpDrupalRestClientToken();
    $uri = "$create_uri?_format=json";
    $args = [
      'body' => $create_content,
      'headers' => [
        'Content-Type' => 'application/json',
        'X-CSRF-Token' => $this->drupalRestToken,
      ],
    ];
    $method = 'post';
    return $this->getGuzzleRequest($uri, $method, $args);
  }

  /**
   * Uploads a file for an entity field using the Drupal REST client.
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
  protected function uploadDrupalRestFileToEntityField(
    string $entity_type_id,
    string $entity_bundle,
    string $field_name,
    string $file_contents,
    string $file_name
  ) : object {
    $this->checkSetUpDrupalRestClientToken();
    $uri = "/file/upload/{$entity_type_id}/{$entity_bundle}/{$field_name}?_format=json";
    $args = [
      'body' => $file_contents,
      'headers' => [
        'Content-Type' => 'application/octet-stream',
        'Content-Disposition' => "file; filename=\"$file_name\"",
        'X-CSRF-Token' => $this->drupalRestToken,
      ],
    ];
    $method = 'post';
    return $this->getGuzzleRequest($uri, $method, $args);
  }

}
