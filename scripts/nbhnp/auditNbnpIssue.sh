#!/usr/bin/env bash
if [[ -z "${1}" ]] || ! [[ -d "$1" ]]; then
  echo "Path [$1] not found, usage: './auditNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 56'"
  exit 1
fi

re='^[0-9]+$'
if [[ -z "${2}" ]] || ! [[ $2 =~ $re ]]; then
  echo "Invalid Parent Entity ID [$2], usage: './auditNbnpIssue.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/ 56'"
  exit 1
fi

cd /home/imaging/NBHP/systems-toolkit || exit 1
vendor/bin/syskit newspapers.lib.unb.ca:audit-tree $2 $1 /mnt/newspapers.lib.unb.ca/prod --instance-uri=https://newspapers.lib.unb.ca
