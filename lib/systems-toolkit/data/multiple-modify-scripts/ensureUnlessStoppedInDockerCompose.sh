#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'IN-289 Set unless-stopped for services in docker-compose' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/ensureUnlessStoppedInDockerCompose.sh --yes --skip-commit-prefix --multi-repo-delay=120
cat ./docker-compose.yml | yq '.services.**.restart = "unless-stopped"' | sponge ./docker-compose.yml

