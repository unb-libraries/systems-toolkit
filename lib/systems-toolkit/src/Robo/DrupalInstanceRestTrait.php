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
   * Set the drupal rest client token for a URI.
   *
   * @throws \Exception
   */
  protected function setUpDrupalRestClientToken() {
    $response = $this->guzzleClient->get($this->drupalRestUri . "/rest/session/token");
    $this->drupalRestToken =  (string) ($response->getBody());
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
    $this->setUpDrupalRestClientToken();
    $create_uri = $this->drupalRestUri . "$create_uri?_format=json";
    $this->say($create_uri);
    $this->drupalRestResponse = $this->guzzleClient
      ->post($create_uri, [
        'auth' => [$this->drupalRestUser, $this->drupalRestPassword],
        'body' => $create_content,
        'headers' => [
          'Content-Type' => 'application/json',
          'X-CSRF-Token' => $this->drupalRestToken
        ],
      ]);
    return json_decode((string) $this->drupalRestResponse->getBody());
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
    $this->setUpDrupalRestClientToken();
    $post_uri = $this->drupalRestUri . "/file/upload/{$entity_type_id}/{$entity_bundle}/{$field_name}?_format=json";
    $this->say($post_uri);
    $this->drupalRestResponse = $this->guzzleClient->post(
      $post_uri,
      [
        'auth' => [$this->drupalRestUser, $this->drupalRestPassword],
        'body' => $file_contents,
        'headers' => [
          'Content-Type' => 'application/octet-stream',
          'Content-Disposition' => "file; filename=\"$file_name\"",
          'X-CSRF-Token' => $this->drupalRestToken
        ],
      ]
    );
    return json_decode((string) $this->drupalRestResponse->getBody());
  }

}
