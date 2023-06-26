#!/usr/bin/env bash
docker kill $(docker ps -a -q)
sleep 5
time ./importNbnpIssue.sh /mnt/nbnp/TheTimes/ 105 --yes
