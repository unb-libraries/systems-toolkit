#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Upstream image drupal:9.x-1.x -> drupal:9.x-2.x' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/setUpstreamDrupalV2.sh --yes
sed -i "s|drupal:9.x-1.x-unblib|drupal:9.x-2.x-unblib|g" .dockworker/dockworker.yml
sed -i "s|drupal:9.x-1.x-unblib|drupal:9.x-2.x-unblib|g" ./Dockerfile
composer config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
