#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Ensure applications have named volumes in docker-compose' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/ensureNamedVolumesDockerCompose.sh --yes --skip-commit-prefix --multi-repo-delay=120
FILE='./docker-compose.yml'

# Ensure Drupal volume is named.
if grep -q '/app/html/sites/default' "$FILE"; then
  sed -i "s|- /app/html/sites/default|- drupal-data:/app/html/sites/default|g" "$FILE"
  cat ./docker-compose.yml | yq '.volumes.drupal-data = {}' | sponge ./docker-compose.yml
fi

# Ensure Drupal mysql volume is named.
if grep -q 'drupal-mysql-lib-unb-ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services.drupal-mysql-lib-unb-ca.volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
fi

# Ensure Drupal solr volume is named.
if grep -q 'drupal-solr-lib-unb-ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services.drupal-solr-lib-unb-ca.volumes |= . + ["solr-data:/opt/solr/server/solr/mycores"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.solr-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'drupal.mysql.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."drupal.mysql.lib.unb.ca".volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'drupal.solr.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."drupal.solr.lib.unb.ca".volumes |= . + ["solr-data:/opt/solr/server/solr/mycores"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.solr-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'dspace.postgres.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."dspace.postgres.lib.unb.ca".volumes |= . + ["postgres-data:/var/lib/postgresql/data/pgdata"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.postgres-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'mediawiki.mysql.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."mediawiki.mysql.lib.unb.ca".volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'mediawiki-mysql-lib-unb-ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services.mediawiki-mysql-lib-unb-ca.volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'omeka.mysql.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."omeka.mysql.lib.unb.ca".volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'ospos.mysql.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."ospos.mysql.lib.unb.ca".volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'ospos-mysql-lib-unb-ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."ospos-mysql-lib-unb-ca".volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'unbscholar.postgres.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."unbscholar.postgres.lib.unb.ca".volumes |= . + ["postgres-data:/var/lib/postgresql/data/pgdata"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.postgres-data = {}' | sponge ./docker-compose.yml
fi

if grep -q 'unbscholar.solr.lib.unb.ca' "$FILE"; then
  cat ./docker-compose.yml | yq '.services."unbscholar.solr.lib.unb.ca".volumes |= . + ["solr-data:/opt/solr/server/solr/mycores"]' | sponge ./docker-compose.yml
  cat ./docker-compose.yml | yq '.volumes.solr-data = {}' | sponge ./docker-compose.yml
fi

# Remove empty key-value sets.
sed -i "s| {}||g" "$FILE"
