#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Add config-yml htaccess to gitignore' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateDrupalGitIgnore.sh --yes --multi-repo-delay=120 --skip-commit-prefix
grep -qxF 'config-yml/.htaccess' .gitignore || echo 'config-yml/.htaccess' >> .gitignore
