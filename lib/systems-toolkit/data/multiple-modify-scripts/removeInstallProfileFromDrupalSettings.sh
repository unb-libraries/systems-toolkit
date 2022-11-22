#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'IN-299 Remove install_profile from base.settings.php' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/removeInstallProfileFromDrupalSettings.sh --yes --multi-repo-delay=120 --skip-commit-prefix
sed -i '/\install profile/d' ./build/settings/base.settings.php
sed -i '/\install_profile/d' ./build/settings/base.settings.php
sed -i -e '/./b' -e :n -e 'N;s/\n$//;tn' ./build/settings/base.settings.php
