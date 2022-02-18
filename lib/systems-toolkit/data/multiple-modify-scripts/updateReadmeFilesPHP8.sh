#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'Update README to reflect new workflow paths' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateReadmeFilesPHP8.sh --yes --multi-repo-delay=120 --skip-commit-prefix
sed -i -E 's|(dockworker.*": ")^4|\1^5|g' ./composer.json
sed -i -E 's|(dockworker.*": ")~4|\1^5|g' ./composer.json
composer install
vendor/bin/dockworker readme:update
git restore composer.json
