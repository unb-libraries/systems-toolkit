#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Automated ctools incompatibility fixes' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/addCToolsRestrictionToDrupalComposer.sh --yes --multi-repo-delay=120 --skip-commit-prefix
# sed -i '/ctools/d' ./config-yml/core.extension.yml config-yml/*.yml
if [ -f "./build/composer.json" ]; then
  if grep -q pathauto "./build/composer.json"; then
    if grep -q ctools "./config-yml/core.extension.yml"; then
      read -p "Verify if ctools needs purging..."
    fi
  fi
fi
