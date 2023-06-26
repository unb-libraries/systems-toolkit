#!/usr/bin/env bash
if [[ -z "${1}" ]] || ! [[ -d "$1" ]]; then
  echo "Path [$1] not found, usage: './importNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 56'"
  exit 1
fi

re='^[0-9]+$'
if [[ -z "${2}" ]] || ! [[ $2 =~ $re ]]; then
  echo "Invalid Parent Entity ID [$2], usage: './importNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 56'"
  exit 1
fi

cd /home/imaging/NBHP/systems-toolkit || exit 1

if [ -z "$3" ]; then
  vendor/bin/syskit newspapers.lib.unb.ca:create-issues-tree $2 $1 --instance-uri=https://newspapers.lib.unb.ca --webtree-path=/mnt/newspapers.lib.unb.ca/prod --threads=12
else
  vendor/bin/syskit newspapers.lib.unb.ca:create-issues-tree $2 $1 --instance-uri=https://newspapers.lib.unb.ca --webtree-path=/mnt/newspapers.lib.unb.ca/prod --threads=12 --yes
fi

