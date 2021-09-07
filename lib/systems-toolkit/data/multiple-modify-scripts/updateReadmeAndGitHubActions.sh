#!/usr/bin/env bash
composer install
vendor/bin/dockworker readme:update
vendor/bin/dockworker ci:update-workflow-file
