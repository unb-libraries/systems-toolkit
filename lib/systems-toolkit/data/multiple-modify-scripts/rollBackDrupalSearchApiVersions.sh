#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'IN-298 Rollbacks search_api -> 1.26 / search_api_solr -> 4.2.8' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/rollBackDrupalSearchApiVersions.sh --yes --multi-repo-delay=120 --skip-commit-prefix
if [ -f "./build/composer.json" ]; then
  if grep -q pathauto "./build/composer.json"; then
    if grep -q search_api "./config-yml/core.extension.yml"; then
      echo "Rolling back search modules..."
      sed -i 's|search_api_solr": "4.2.9"|search_api_solr": "4.2.8"|g' build/composer.json
      sed -i 's|search_api": "1.27"|search_api": "1.26"|g' build/composer.json
    fi
  fi
fi
