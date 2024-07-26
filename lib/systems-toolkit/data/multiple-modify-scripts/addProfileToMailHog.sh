#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal' 'Add profiles to docker-compose' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/addProfileToMailHog.sh --yes --skip-commit-prefix --multi-repo-delay=120
FILE='./docker-compose.yml'

if grep -q 'mailhog' "$FILE"; then
  if grep -q 'profiles:' "$FILE"; then
    echo "Skipping previously-patched $FILE..."
  else
    cat "$FILE" | yq '.services.mailhog.profiles = ["mailhog"]' | sponge ./docker-compose.yml
  fi
else
  echo "Skipping $FILE, mailhog service not found..."
fi
