#!/usr/bin/env bash
set -e

if [[ -z "${1}" ]] || ! [[ -d "$1" ]]; then
  echo "ERROR! Path [$1] not found, usage: './auditIssueMetadata.sh /mnt/nbnp/TheWeeklyChronicle/WC_1824/'"
  exit 1
fi

echo "Auditing Issue Metadata in $1 for syntax errors..."

COUNTER=0
for i in $(find "$1" -type f -name 'metadata.php'); do
  echo $i
  echo "<?php" > /tmp/imaging.php
  cat "$i" >> /tmp/imaging.php
  php -f /tmp/imaging.php
  COUNTER=$(( COUNTER + 1 ))
done


if (( $COUNTER > 0 )); then
  echo "Metadata files within the path [$1] do not contain any syntax errors."
  exit 0;
fi

echo "ERROR! No metadata.php files found in provided path [$1]"
exit 1;
