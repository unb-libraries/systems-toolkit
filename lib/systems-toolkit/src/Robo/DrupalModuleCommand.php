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

  public const CHANGELOG_CACHE_TIME = 3600;
  public const CHANGELOG_CSS_SELECTOR = '#release-notes .field-items';
  public const CHANGELOG_URI = 'https://www.drupal.org/project/%s/releases/8.x-%s';

  /**
   * Retrieves the changelog from a drupal module release.
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
  protected function getModuleChangelog(
    string $module,
    string $version
  ) : string {
    $commit_text = '';
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
      catch (BadResponseException) {
        $changelog_uri = str_replace('8.x-', '', $changelog_uri);
        try {
          $response = $client->request('GET', $changelog_uri);
        }
        catch (BadResponseException) {
          return NULL;
        }
      }
      $htmlResponse = $response->getBody()->__toString();
      $crawler = new Crawler($htmlResponse);
      $changelog_node = $crawler->filter(self::CHANGELOG_CSS_SELECTOR);
      if ($changelog_node->count() > 0) {
        $converter = new HtmlConverter();
        try {
          return $converter->convert($changelog_node->outerHtml());
        }
        catch (\Exception) {
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
   * Gets the changelog from a drupal module release.
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
  public static function moduleChangeLog(
    string $module,
    string $version
  ) : string {
    $obj = new static();
    return $obj->getModuleChangelog(
      $module,
      $version
    );
  }

}
