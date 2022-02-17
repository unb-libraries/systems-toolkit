#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'Order composer.json files appropriately' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/sortComposerFilesAlphabetically.sh --yes --multi-repo-delay=60 --skip-commit-prefix
if [ -f "./composer.json" ]; then
    jq --sort-keys . ./composer.json > /tmp/composer.json && mv /tmp/composer.json ./composer.json
fi

if [ -f "./build/composer.json" ]; then
    jq --sort-keys . ./build/composer.json > /tmp/composer.json && mv /tmp/composer.json ./build/composer.json
fi
