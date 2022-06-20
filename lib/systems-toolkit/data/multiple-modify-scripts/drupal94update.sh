#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' '9.4.0 Update' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/drupal94update.sh --yes
cat ./build/composer.json | jq '.require."drupal/core"="9.4.0"' | sponge ./build/composer.json
cat ./config-yml/core.extension.yml | yq '.module.mysql = 0' | yq 'sort_keys(.module)' | sponge ./config-yml/core.extension.yml
