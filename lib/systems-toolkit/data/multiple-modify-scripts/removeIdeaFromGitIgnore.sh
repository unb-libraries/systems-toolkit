#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Remove idea from gitignore' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateDrupalGitIgnore.sh --yes --multi-repo-delay=120 --skip-commit-prefix
sed -i '/\.idea/d' .gitignore
