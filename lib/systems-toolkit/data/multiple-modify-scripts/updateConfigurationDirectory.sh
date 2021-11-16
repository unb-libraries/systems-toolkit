#!/usr/bin/env bash
sed -i "s|^\$settings\['config_sync_directory'\].*|\$settings[\'config_sync_directory\'] = \'DRUPAL_CONFIGURATION_DIR\';|g" build/settings/base.settings.php
