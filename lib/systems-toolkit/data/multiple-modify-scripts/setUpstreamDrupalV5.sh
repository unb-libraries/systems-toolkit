#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'D9 Upstream image -> 9.x branch' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/setUpstreamDrupalV5.sh --yes
sed -i "s|drupal:8.x-1.x-unblib|drupal:9.x-2.x-unblib|g" .dockworker/dockworker.yml
sed -i "s|drupal:8.x-2.x-unblib|drupal:9.x-2.x-unblib|g" .dockworker/dockworker.yml
sed -i "s|drupal:8.x-3.x-unblib|drupal:9.x-2.x-unblib|g" .dockworker/dockworker.yml

sed -i "s|drupal:8.x-1.x-unblib|drupal:9.x-2.x-unblib|g" ./Dockerfile
sed -i "s|drupal:8.x-2.x-unblib|drupal:9.x-2.x-unblib|g" ./Dockerfile
sed -i "s|drupal:8.x-3.x-unblib|drupal:9.x-2.x-unblib|g" ./Dockerfile
