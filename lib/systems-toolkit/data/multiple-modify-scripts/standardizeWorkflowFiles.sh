#!/bin/bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'Update workflow files to current standard' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/standardizeWorkflowFiles.sh --yes --multi-repo-delay=120 --skip-commit-prefix
sed -i "s|d8:|drupal:|g" .github/workflows/test-suite.yaml
sed -i "s|d9:|drupal:|g" .github/workflows/test-suite.yaml
sed -i "s|deployment-workflow.yaml@4.x|deployment-workflow.yaml@5.x|g" .github/workflows/test-suite.yaml
git mv .github/workflows/test-suite.yaml .github/workflows/deployment-workflow.yaml
