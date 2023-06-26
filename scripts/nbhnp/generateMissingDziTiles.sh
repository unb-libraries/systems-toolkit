#!/usr/bin/env bash
shopt -s globstar

echo "" > missing_dzi_issues.txt
for f in /mnt/newspapers.lib.unb.ca/prod/files/serials/pages/*-*.jpg; do 
  DZI_NAME=${f%.jpg}.dzi
  if [ ! -f "$DZI_NAME" ]; then
    basename "$f" | awk -F- '{print $1}' >> missing_dzi_issues.txt
  fi
done

echo "" > unique_missing_dzi_issues.txt
sort missing_dzi_issues.txt | uniq > unique_missing_dzi_issues.txt

ISSUES_TO_PROCESS=$(cat unique_missing_dzi_issues.txt | wc -l)
cd /home/imaging/NBHP/systems-toolkit

PROCESSED_ISSUES=0;
while read ISSUE_ID; do
  PROCESSED_ISSUES=$((PROCESSED_ISSUES+1))
  if [ ! -z "$ISSUE_ID" ];
  then
    echo "Processing $ISSUE_ID [$PROCESSED_ISSUES/$ISSUES_TO_PROCESS]..."
    vendor/bin/syskit newspapers.lib.unb.ca:issue:generate-dzi /mnt/newspapers.lib.unb.ca/prod "$ISSUE_ID" --threads=12
  fi
done <../unique_missing_dzi_issues.txt
