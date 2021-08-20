#!/usr/bin/env bash
rm -rf ./tests/behat
composer install
vendor/bin/dockworker dockworker:gh-actions:update
