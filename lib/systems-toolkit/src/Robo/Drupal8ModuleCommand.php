<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use GuzzleHttp\Client;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\Cache\ItemInterface;


/**
 * Class for Drupal8ModuleCommand Robo commands.
 */
class Drupal8ModuleCommand extends SystemsToolkitCommand {

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
   * @command drupal:8:module:changelog
   *
   * @return string
   *   The changelog for the module.
   */
  public function getModuleChangelog($module, $version) {
    $cache = new FilesystemAdapter();
    $cache_tag = "$module$version";
    // Ensure we use only pure version for URI.
    $version = str_replace('8.x-', '', $version);

    $raw_message = $cache->get($cache_tag, function (ItemInterface $item) use ($module, $version) {
      $item->expiresAfter(self::CHANGELOG_CACHE_TIME);
      $client = new Client();
      $changelog_uri = sprintf(
        self::CHANGELOG_URI,
        $module,
        $version
      );
      $response = $client->request('GET', $changelog_uri);
      $htmlResponse = $response->getBody()->__toString();
      $crawler = new Crawler($htmlResponse);
      $crawler = $crawler->filter(self::CHANGELOG_CSS_SELECTOR);
      $converter = new HtmlConverter();
      return $converter->convert($crawler->outerHtml());
    });

    // Tidy-up formatting.
    $commit_text = preg_replace('/\[(.*?)\]\(.*?\)/', "[$1]", $raw_message);
    $commit_text = preg_replace('/ +/', ' ', $commit_text);
    $commit_text = str_replace('[\\#', '[#', $commit_text);
    $commit_text = str_replace('\\\\\\\\', '\\', $commit_text);
    $commit_text = htmlspecialchars_decode($commit_text);

    return trim(
      strip_tags($commit_text)
    );
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
