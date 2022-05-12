#!/usr/bin/env bash
#
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'Wrap 0-leading deprecated octal values in YAML in quotes' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updatek8sDeploymentYamlOctalValuesToString.sh --yes --skip-commit-prefix
find ./.dockworker/deployment -type f -name "*.yaml" -exec sed -i "s|: \(0[0-9]\+\)$|\: '\1'|g" {} \;
