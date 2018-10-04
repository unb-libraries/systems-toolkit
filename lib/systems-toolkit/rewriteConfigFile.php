<?php
/*
 * Rewrite the newspapers metadata file into something sane.
 */

if (file_exists($argv[1])) {
  print_r($argv[1]);

  $metadata_contents = file_get_contents($argv[1]);
  $metadata_contents = str_replace('<?php', '', $metadata_contents);
  eval($metadata_contents);

  $config = [
    'title' => ISSUE_TITLE,
    'volume' => ISSUE_VOLUME,
    'issue' => ISSUE_ISSUE,
    'edition' => ISSUE_EDITION,
    'date' => date( "Y-m-d", ISSUE_DATE),
    'missing' => MISSING_PAGES,
    'errata' => ISSUE_ERRATA,
    'language' => ISSUE_LANGUAGE,
    'media' => SOURCE_MEDIA,
  ];

  file_put_contents($argv[1] . ".json", json_encode($config));
}
