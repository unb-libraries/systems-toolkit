#!/usr/bin/env bash
# vendor/bin/syskit github:multiple-repo:script-modify '' 'dockworker' 'Update testing deployment to standardize and provide mongo compatibility' ~/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/updateTestingDeployments.sh --yes --skip-commit-prefix --multi-repo-delay=120
FILE='./.dockworker/deployment/k8s/prod/testing.yaml'
SITE_SLUG="${1//./-}"

if [ -f "$FILE" ]; then
  cat $FILE | yq --prettyPrint '.metadata.namespace = "testing"' | sponge $FILE
  cat $FILE | yq --prettyPrint '.spec.jobTemplate.spec.backoffLimit = 0' | sponge $FILE
  cat $FILE | yq --prettyPrint 'del(.spec.jobTemplate.spec.template.spec.nodeSelector.deployenv)' | sponge $FILE
  cat $FILE | yq --prettyPrint '.spec.jobTemplate.spec.template.spec.nodeSelector.environment = "testing"' | sponge $FILE
  cat $FILE | yq --prettyPrint 'del(.spec.jobTemplate.spec.template.spec.containers[].env[] | select(.name == "DEPLOY_ENV"))' | sponge $FILE
  cat $FILE | yq --prettyPrint '.spec.jobTemplate.spec.template.spec.containers[].env += {"name": "DEPLOY_ENV", "value": "testing"}' | sponge $FILE
  cat $FILE | yq --prettyPrint 'del(.spec.jobTemplate.spec.template.spec.containers[].env[] | select(.name == "MONGODB_CONNECT_URI"))' | sponge $FILE
  cat $FILE | yq --prettyPrint '.spec.jobTemplate.spec.template.spec.containers[].env += {"name": "MONGODB_CONNECT_URI", "valueFrom": {"secretKeyRef" : {"name": "mongodb", "key": "connect-uri"}}}' | sponge $FILE
  cp $FILE ~/gitDev/kubernetes-metadata/services/$SITE_SLUG/prod/testing-$SITE_SLUG.Cronjob.yaml
fi
