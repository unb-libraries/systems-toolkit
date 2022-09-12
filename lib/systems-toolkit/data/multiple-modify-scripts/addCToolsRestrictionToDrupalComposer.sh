#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Add new security/config to drupal composer.json' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/addNewSecurityRequirementsToDrupalComposer.sh --yes --multi-repo-delay=120 --skip-commit-prefix
if [ -f "./build/composer.json" ]; then
  cd ./build
  composer config allow-plugins.composer/installers true
  composer config allow-plugins.cweagans/composer-patches true
  composer config allow-plugins.drupal/core-composer-scaffold true
  composer config sort-packages true
  composer config discard-changes true
  cd ..
  jq --sort-keys . ./build/composer.json > /tmp/composer.json && mv /tmp/composer.json ./build/composer.json
fi

if [ -f "./composer.json" ]; then
  composer config minimum-stability dev
  composer config prefer-stable true
  jq --sort-keys . ./composer.json > /tmp/composer.json && mv /tmp/composer.json ./composer.json
fi
