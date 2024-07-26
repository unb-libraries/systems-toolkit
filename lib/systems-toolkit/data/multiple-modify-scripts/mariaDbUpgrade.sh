#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal' 'Mysql -> MariaDB' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/mariaDbUpgrade.sh --yes
sed -i "s|mysql:5.7|mariadb:10.11|g" ./docker-compose.yml

