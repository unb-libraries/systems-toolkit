<?php

namespace UnbLibraries\SystemsToolkit\Robo;

use UnbLibraries\SystemsToolkit\KubeExecTrait;
use UnbLibraries\SystemsToolkit\Robo\BasicKubeCommand;

/**
 * Robo commands for newspapers.lib.unb.ca's missing page asset scan.
 */
class NewspapersMissingAssetScanCommand extends BasicKubeCommand {

  use KubeExecTrait;

  public const NEWSPAPERS_FULL_SLUG = 'newspapers-lib-unb-ca';
  public const NEWSPAPERS_NAMESPACE = 'prod';
  public const ASSET_TYPES = ['dzi', 'pdf', 'all'];

  /**
   * Makes newspapers.lib.unb.ca recheck every page image for an asset.
   *
   * The site remembers how far its missing-asset scan has confirmed pages
   * have their DZI or PDF files, and later scans start from there. Reset it
   * after deleting assets of pages the scan has already passed.
   *
   * @param string $asset
   *   The asset scan to reset: dzi, pdf or all.
   *
   * @throws \Exception
   *
   * @command newspapers.lib.unb.ca:reset-missing-asset-scan
   * @usage newspapers.lib.unb.ca:reset-missing-asset-scan dzi
   */
  public function resetMissingAssetScan(string $asset) : void {
    if (!in_array($asset, self::ASSET_TYPES, TRUE)) {
      throw new \Exception(sprintf('Unknown asset [%s]; use %s.', $asset, implode(', ', self::ASSET_TYPES)));
    }
    $this->setCurKubePodsFromSelector(['app.kubernetes.io/instance=' . self::NEWSPAPERS_FULL_SLUG], [self::NEWSPAPERS_NAMESPACE]);
    if (empty($this->kubeCurPods)) {
      throw new \Exception('No running newspapers.lib.unb.ca pod was found.');
    }

    // The scan position is stored in the site's database, so one pod is enough.
    $pod = reset($this->kubeCurPods);
    $result = $this->kubeExecPod($pod, 'drush', '-i', ['digital-serial-page:reset-missing-asset-scan', $asset]);
    if (!$result->wasSuccessful()) {
      throw new \Exception(sprintf('Resetting the %s scan in %s failed.', $asset, $pod->metadata->name));
    }
  }

}
