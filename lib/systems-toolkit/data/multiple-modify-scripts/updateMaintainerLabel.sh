#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'IN-348 Migrate MAINTAINER in Dockerfile' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateMaintainerLabel.sh --yes -skip-commit-prefix
#   org.opencontainers.image.authors="UNB Libraries <libsupport@unb.ca>" \

# If the Dockerfile has a MAINTAINER declaration, pause and wait for input.
if grep -q "MAINTAINER" Dockerfile; then
  echo "MAINTAINER declaration found in Dockerfile."
  echo "Please update the MAINTAINER declaration to the LABEL declaration."
  read -s -n 1 -p "Press any key to continue . . ."
fi

