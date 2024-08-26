#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'IN-347 Update ENV declarations' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateEnvDeclarations.sh --yes
sed -i "s|ENV \([A-Z_]*\) \(.*\)$|ENV \1=\"\2\"|g" Dockerfile
