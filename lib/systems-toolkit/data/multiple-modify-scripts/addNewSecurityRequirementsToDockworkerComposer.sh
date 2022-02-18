#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'Add new security/config to dockworker composer.json' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/addNewSecurityRequirementsToDockworkerComposer.sh --yes
if [ -f "./composer.json" ]; then
  composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
  jq --sort-keys . ./composer.json > /tmp/composer.json && mv /tmp/composer.json ./composer.json
fi
