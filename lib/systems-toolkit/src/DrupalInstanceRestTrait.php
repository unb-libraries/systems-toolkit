<?php

namespace UnbLibraries\SystemsToolkit;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Promise\PromiseInterface;
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
   * The token to use when interacting with the REST API.
   *
   * @var string
   */
  protected string $drupalRestToken;

  /**
   * The response from the current Drupal REST API request.
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
  public function setDrupalRestPassword(): void {
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
  public function setDrupalRestUser(): void {
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
   *
   * @return array
   *   The JSON object returned from the server.
   */
  protected function getDrupalRestEntity(
    string $entity_uri,
    bool $silent = FALSE
  ) : array {
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
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
    catch (BadResponseException $e) {
      $status = $e->getResponse()->getStatusCode();
      // Credential/permission failures will not succeed on retry, and
      // repeated attempts trigger Drupal's failed-login flood lockout.
      if (in_array($status, [401, 403])) {
        throw new \Exception(sprintf('The REST request to %s failed with HTTP %s (not retried).', $endpoint_uri, $status), $status, $e);
      }
      $retry_counter++;
      if ($retry_on_error && $retry_counter < $max_retries) {
        // Back off 2, 4, 8, 16 seconds to ride out transient errors (e.g. 503).
        $delay = 2 ** $retry_counter;
        $this->say(sprintf('HTTP %s from %s, retrying in %ss (%s/%s)', $status, $endpoint_uri, $delay, $retry_counter, $max_retries - 1));
        sleep($delay);
        return $this->getGuzzleRequest($uri, $method, $args, $silent, $retry_on_error, $retry_counter, $max_retries);
      }
    }
    throw new \Exception(sprintf('The REST request to %s failed.', $endpoint_uri));
  }

  /**
   * Sets the Drupal REST client token for a URI.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  /**
   * Starts an authenticated REST request, returning a promise of the JSON.
   *
   * Retries like getGuzzleRequest(): exponential backoff on errors, but no
   * retries on 401/403.
   *
   * @param string $method
   *   The HTTP method.
   * @param string $uri
   *   The URI, relative to the instance URI, including any query string.
   * @param array $args
   *   Guzzle request options (body, headers).
   * @param int $attempt
   *   The retry attempt number.
   * @param int $max_retries
   *   The maximum number of attempts.
   *
   * @return \GuzzleHttp\Promise\PromiseInterface
   *   A promise resolving to the decoded JSON response.
   */
  protected function getDrupalRestRequestPromise(
    string $method,
    string $uri,
    array $args = [],
    int $attempt = 0,
    int $max_retries = 5
  ) : PromiseInterface {
    $this->checkSetUpDrupalRestClientToken();
    $endpoint_uri = $this->drupalRestUri . $uri;
    $request_args = array_merge_recursive(
      [
        'auth' => [$this->drupalRestUser, $this->drupalRestPassword],
        'headers' => ['X-CSRF-Token' => $this->drupalRestToken],
      ],
      $args
    );
    if ($attempt > 0) {
      // Back off 2, 4, 8, 16 seconds (Guzzle delay is in milliseconds).
      $request_args['delay'] = (2 ** $attempt) * 1000;
    }

    return $this->guzzleClient->requestAsync($method, $endpoint_uri, $request_args)->then(
      fn($response) => json_decode((string) $response->getBody(), NULL, 512, JSON_THROW_ON_ERROR),
      function ($reason) use ($method, $uri, $args, $attempt, $max_retries, $endpoint_uri) {
        if ($reason instanceof BadResponseException) {
          $status = $reason->getResponse()->getStatusCode();
          if (in_array($status, [401, 403])) {
            throw new \Exception(sprintf('The REST request to %s failed with HTTP %s (not retried).', $endpoint_uri, $status), $status, $reason);
          }
          if ($attempt + 1 < $max_retries) {
            $this->say(sprintf('HTTP %s from %s, retrying (%s/%s)', $status, $endpoint_uri, $attempt + 1, $max_retries - 1));
            return $this->getDrupalRestRequestPromise($method, $uri, $args, $attempt + 1, $max_retries);
          }
        }
        $message = $reason instanceof \Throwable ? $reason->getMessage() : (string) $reason;
        throw new \Exception(sprintf('The REST request to %s failed. %s', $endpoint_uri, $message));
      }
    );
  }

  protected function setUpDrupalRestClientToken(): void {
    $response = $this->guzzleClient->get($this->drupalRestUri . "/session/token");
    $this->drupalRestToken = (string) ($response->getBody());
  }

  /**
   * Sets the Drupal REST client token for a URI if it is unset.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function checkSetUpDrupalRestClientToken(): void {
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
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
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
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
    $uri = "/file/upload/$entity_type_id/$entity_bundle/$field_name?_format=json";
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
