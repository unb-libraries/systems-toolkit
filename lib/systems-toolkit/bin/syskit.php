<?php

/**
 * @file
 * Execute commands via Robo.
 */

use Robo\Robo;

// Discover all commands in Robo Directory.
$discovery = new \Consolidation\AnnotatedCommand\CommandFileDiscovery();
$discovery->setSearchPattern('*Command.php');
$coreClasses = $discovery->discover("$repo_root/vendor/unb-libraries/systems-toolkit/src/Robo", 'UnbLibraries\SystemsToolkit\Robo');

$statusCode = Robo::run(
  $_SERVER['argv'],
  $coreClasses,
  'SystemsToolkit',
  '1.0.0'
);

exit($statusCode);
