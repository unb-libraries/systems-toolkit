#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'Update README, gh-workflow' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateReadmeAndGitHubActions.sh --yes --skip-commit-prefix
composer install
vendor/bin/dockworker readme:file:write
vendor/bin/dockworker ci:workflow:file:write
