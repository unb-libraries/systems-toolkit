#!/usr/bin/env bash
composer install
vendor/bin/dockworker local:config:remote-sync prod
