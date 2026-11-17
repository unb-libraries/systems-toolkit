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
   * The number of times a listing request is sent before giving up.
   *
   * The site stops scanning after 40s and answers 503 if it found nothing yet,
   * saving how far it got, so each retry continues the scan.
   */
  protected int $newspapersMissingAssetAttempts = 10;

  /**
   * Gets pages missing an asset from a newspapers.lib.unb.ca listing.
   *
   * The site lists these only to the account that ingests pages, as each
   * request can walk every page image on disk, so the request sends the
   * syskit.drupal.rest credentials over basic auth. Retries while the site
   * answers 503 or 504, up to $newspapersMissingAssetAttempts requests.
   *
   * Always asks the site to skip no rows, so the site starts from its saved
   * scan position. Reset that with newspapers.lib.unb.ca:reset-missing-asset-scan.
   *
   * @param string $url
   *   The listing URL, without the limit and skip arguments.
   * @param int|string $limit
   *   The number of pages to return. The site returns at most 500.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \JsonException
   *
   * @return array
   *   The pages, keyed by image file ID.
   */
  protected function getNewspapersPagesMissingAsset(string $url, int|string $limit) : array {
    $user = Robo::Config()->get('syskit.drupal.rest.user');
    $password = Robo::Config()->get('syskit.drupal.rest.password');
    if (empty($user) || empty($password)) {
      throw new \Exception('Set syskit.drupal.rest.user and syskit.drupal.rest.password: newspapers.lib.unb.ca lists missing assets only to the account that ingests pages.');
    }

    for ($attempt = 1; ; $attempt++) {
      try {
        $response = (new Client())->request('GET', "$url/$limit/0", [
          'auth' => [$user, $password],
        ]);
        break;
      }
      catch (BadResponseException $e) {
        $status = $e->getResponse()->getStatusCode();
        if (in_array($status, [401, 403])) {
          throw new \Exception(sprintf("The request to %s failed with HTTP %s. Check syskit.drupal.rest.user and syskit.drupal.rest.password; the account needs the 'add serial page entities' permission.", $url, $status), $status, $e);
        }
        // 503: the scan ran out of time before finding a page. 504: the proxy
        // gave up first. The site saves its scan progress when a scan stops,
        // so a retry continues from there.
        if (!in_array($status, [503, 504]) || $attempt >= $this->newspapersMissingAssetAttempts) {
          throw $e;
        }
        $wait = (int) ($e->getResponse()->getHeaderLine('Retry-After') ?: 0);
        $this->say(sprintf('The site is still scanning for missing assets (HTTP %s); retrying (%d of %d)...', $status, $attempt + 1, $this->newspapersMissingAssetAttempts));
        sleep(max($wait, 0));
      }
    }
    return json_decode((string) $response->getBody(), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Prints pages missing an asset without generating anything.
   *
   * @param array $pages
   *   The pages, as returned by getNewspapersPagesMissingAsset().
   */
  protected function printNewspapersPagesMissingAsset(array $pages) : void {
    if (empty($pages)) {
      $this->say('No missing files found.');
      return;
    }
    $rows = [];
    foreach ($pages as $page) {
      $rows[] = [$page['fid'], $page['title_id'], $page['issue_id'], $page['rel_image_path']];
    }
    $this->io()->table(['File ID', 'Title', 'Issue', 'Image'], $rows);
    $this->say(sprintf('%d page(s) missing the asset.', count($pages)));
  }

}
