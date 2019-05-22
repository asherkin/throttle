#!/usr/bin/env php
<?php

$wh = exec('tput lines');

$total = 0;
$hashstats = array();

while ($s = fgets(STDIN))
{
  list($crash, $hash) = explode(': ', trim($s), 2);
  $crash = basename($crash, '.dmp');

  if (!isset($hashstats[$hash])) {
    $hashstats[$hash] = 1;
  } else {
    $hashstats[$hash] += 1;
  }

  $total += 1;

  echo chr(27).chr(91).'H'.chr(27).chr(91).'J';

  ksort($hashstats);
  arsort($hashstats);
  print_r(array_slice($hashstats, 0, $wh - 4, true));

  echo 'Total: '.$total.' Unique: '.count($hashstats).PHP_EOL;
}
