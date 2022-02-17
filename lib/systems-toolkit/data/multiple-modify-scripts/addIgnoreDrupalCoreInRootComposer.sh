#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Ignore drupal/core when installing theme for dockworker' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/addIgnoreDrupalCoreInRootComposer.sh --yes
if grep -q drupal/ "composer.json"; then
  jq '.replace."drupal/core" = "*"' composer.json > /tmp/composer.json && mv /tmp/composer.json .
fi
