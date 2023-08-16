<?php
/*
 * Rewrite the newspapers metadata file into something sane.
 */

if (file_exists($argv[1])) {
  print_r($argv[1]);

  $metadata_contents = file_get_contents($argv[1]);
  $metadata_contents = str_replace('<?php', '', $metadata_contents);

  // Reverse month and day in file. This is due to an error in standards.
  fixDate($metadata_contents);

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
    'supplement_title' => ISSUE_SUPPLEMENT_TITLE,
  ];

  file_put_contents($argv[1] . ".json", json_encode($config));
}

function fixDate(&$metadata_contents) {
  $matches=[];
  preg_match('/mktime\((.*?)\)/', $metadata_contents, $matches);
  if (!empty($matches[1])) {
    $date_array = explode(',', $matches[1]);
    $temp_val = $date_array[3];
    $date_array[3] = $date_array[4];
    $date_array[4] = $temp_val;
    $new_date_string = implode(',', $date_array);
    $new_string = "mktime($new_date_string)";
    $metadata_contents = str_replace($matches[0], $new_string, $metadata_contents);
  }
}
