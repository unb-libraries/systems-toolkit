#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Ensure applications have named volumes in docker-compose' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/ensureNamedVolumesDockerCompose.sh --yes --skip-commit-prefix --multi-repo-delay=120
FILE='./docker-compose.yml'

# Ensure Drupal volume is named.
if grep -q '/app/html/sites/default' "$FILE"; then
  if grep -q 'drupal-data:' "$FILE"; then
    echo "Skipping previously-patched drupal-data..."
  else
    sed -i "s|- /app/html/sites/default|- drupal-data:/app/html/sites/default|g" "$FILE"
    cat ./docker-compose.yml | yq '.volumes.drupal-data = {}' | sponge ./docker-compose.yml
  fi
fi

# Ensure Drupal mysql volume is named.
if grep -q 'drupal-mysql-lib-unb-ca' "$FILE"; then
  if grep -q 'mysql-data:' "$FILE"; then
    echo "Skipping previously-patched mysql-data..."
  else
    cat ./docker-compose.yml | yq '.services.drupal-mysql-lib-unb-ca.volumes |= . + ["mysql-data:/var/lib/mysql"]' | sponge ./docker-compose.yml
    cat ./docker-compose.yml | yq '.volumes.mysql-data = {}' | sponge ./docker-compose.yml
  fi
fi

# Ensure Drupal solr volume is named.
if grep -q 'drupal-solr-lib-unb-ca' "$FILE"; then
  if grep -q 'solr-data:' "$FILE"; then
    echo "Skipping previously-patched solr-data..."
  else
    cat ./docker-compose.yml | yq '.services.drupal-solr-lib-unb-ca.volumes |= . + ["solr-data:/opt/solr/server/solr/mycores"]' | sponge ./docker-compose.yml
    cat ./docker-compose.yml | yq '.volumes.solr-data = {}' | sponge ./docker-compose.yml
  fi
fi

# Remove empty key-value sets.
sed -i "s| {}||g" "$FILE"
