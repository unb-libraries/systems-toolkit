#!/usr/bin/env bash
#
# vendor/bin/syskit github:multiple-repo:script-modify '' 'drupal9' 'Migrate k8s deployment cron.yaml to cronjob.yaml' /home/jsanford/gitDev/systems-toolkit/lib/systems-toolkit/data/multiple-modify-scripts/renameCronToCronJob.sh --yes --skip-commit-prefix
for ENV in dev prod; do
  if [ -f ".dockworker/deployment/k8s/$ENV/cron.yaml" ]; then
    mv ".dockworker/deployment/k8s/$ENV/cron.yaml" ".dockworker/deployment/k8s/$ENV/cronjob.yaml"
  fi
done
