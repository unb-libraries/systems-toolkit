<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Cache\ItemInterface;


/**
 * Class for DrupalModuleCommand Robo commands.
 */
class DrupalModuleCommand extends SystemsToolkitCommand {

  const CHANGELOG_CACHE_TIME = 3600;
  const CHANGELOG_CSS_SELECTOR = '#release-notes .field-items';
  const CHANGELOG_URI = 'https://www.drupal.org/project/%s/releases/8.x-%s';

  /**
   * Display the changelog from a drupal module release.
   *
   * @param string $module
   *   Modules to query.
   * @param string $version
   *   Version to query.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   *
   * @command drupal:module:changelog
   *
   * @return string
   *   The changelog for the module.
   */
  public function getModuleChangelog($module, $version) {
    $commit_text = NULL;
    $cache = new FilesystemAdapter();
    $cache_tag = "$module$version";

    // Ensure we use only pure version for URI.
    $version = str_replace('8.x-', '', $version);
    $changelog_uri = sprintf(
      self::CHANGELOG_URI,
      $module,
      $version
    );

    $raw_message = $cache->get($cache_tag, function (ItemInterface $item) use ($changelog_uri) {
      $item->expiresAfter(self::CHANGELOG_CACHE_TIME);
      $client = new Client();
      try {
        $response = $client->request('GET', $changelog_uri);
      }
      catch (BadResponseException $e) {
        $changelog_uri = str_replace('8.x-', '', $changelog_uri);
        try {
          $response = $client->request('GET', $changelog_uri);
        }
        catch (BadResponseException $e) {
          return NULL;
        }
      }
      $htmlResponse = $response->getBody()->__toString();
      $crawler = new Crawler($htmlResponse);
      $changelog_node = $crawler->filter(self::CHANGELOG_CSS_SELECTOR);
      if ($changelog_node->count() > 0 ) {
        $converter = new HtmlConverter();
        try {
          $message = $converter->convert($changelog_node->outerHtml());
          return $message;
        }
        catch (Exception $e) {
          return NULL;
        }
      }
      return NULL;
    });

    // Tidy-up formatting.
    if (!empty($raw_message)) {
      $commit_text = preg_replace('/\[(.*?)\]\(.*?\)/', "[$1]", $raw_message);
      $commit_text = preg_replace('/ +/', ' ', $commit_text);
      $commit_text = str_replace('[\\#', '[#', $commit_text);
      $commit_text = str_replace('\\\\\\\\', '\\', $commit_text);
      $commit_text = str_replace('\_', '_', $commit_text);
      $commit_text = trim(
        strip_tags(
          htmlspecialchars_decode($commit_text)
        )
      );
      // Add a link to the release page.
      $commit_text = sprintf(
        "(Obtained from : %s) \n\n%s",
        $changelog_uri,
        $commit_text
      );
    }

    return $commit_text;
  }

  /**
   * Get the changelog from a drupal module release.
   *
   * @param string $module
   *   Modules to query.
   * @param string $version
   *   Version to query.
   *
   * @throws \Exception
   * @throws \Psr\Cache\InvalidArgumentException
   *
   * @return string
   *   The changelog for the module.
   */
  public static function moduleChangeLog($module, $version) {
    $obj = new static();
    return $obj->getModuleChangelog($module, $version);
  }

}
