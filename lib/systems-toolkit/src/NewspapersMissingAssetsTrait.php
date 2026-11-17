<?php

namespace UnbLibraries\SystemsToolkit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Robo\Robo;

/**
 * Trait for listing newspapers.lib.unb.ca pages that lack generated assets.
 */
trait NewspapersMissingAssetsTrait {

  /**
   * Gets pages missing an asset from a newspapers.lib.unb.ca listing.
   *
   * The site lists these only to the account that ingests pages, as each
   * request can walk every page image on disk, so the request sends the
   * syskit.drupal.rest credentials over basic auth.
   *
   * @param string $url
   *   The listing URL, without the limit and skip arguments.
   * @param int|string $limit
   *   The number of pages to return. The site returns at most 500.
   * @param int|string $skip
   *   The number of page images to skip evaluating.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
   *
   * @return array
   *   The pages, keyed by image file ID.
   */
  protected function getNewspapersPagesMissingAsset(string $url, int|string $limit, int|string $skip) : array {
    $user = Robo::Config()->get('syskit.drupal.rest.user');
    $password = Robo::Config()->get('syskit.drupal.rest.password');
    if (empty($user) || empty($password)) {
      throw new \Exception('Set syskit.drupal.rest.user and syskit.drupal.rest.password: newspapers.lib.unb.ca lists missing assets only to the account that ingests pages.');
    }

    try {
      $response = (new Client())->request('GET', "$url/$limit/$skip", [
        'auth' => [$user, $password],
      ]);
    }
    catch (BadResponseException $e) {
      $status = $e->getResponse()->getStatusCode();
      if (in_array($status, [401, 403])) {
        throw new \Exception(sprintf("The request to %s failed with HTTP %s. Check syskit.drupal.rest.user and syskit.drupal.rest.password; the account needs the 'add serial page entities' permission.", $url, $status), $status, $e);
      }
      throw $e;
    }
    return json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
  }

}
