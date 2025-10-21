#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal' 'Update health_check to 3.x' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateHealthCheckto3.sh --yes --multi-repo-delay=120 --skip-commit-prefix
cd build
sed -i -E 's|"drupal/health_check": ".*"|"drupal/health_check": "^3"|g' ./composer.json
composer update drupal/health_check --with-all-dependencies
rm -rf core modules themes vendor libraries
