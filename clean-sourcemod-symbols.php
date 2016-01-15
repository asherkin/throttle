#!/usr/bin/env php
<?php // vim :set ts=4 sw=4 sts=4 et :

// This can't use the Throttle libs because it has to run on web01 as well.

$root = './symbols/sourcemod/';
if ($argc > 1) {
  $root = $argv[1];
}

if (!is_dir($root)) {
  error_log('Not a directory: ' . $root);
  exit();
}

if (substr($root, -1) !== '/') {
  $root .= '/';
}

$moduleIterator = new DirectoryIterator($root);
foreach ($moduleIterator as $module) {
  if ($module->isDot() || !$module->isDir()) {
    continue;
  }

  $moduleName = $module->getFilename();

  $symbolFile = $moduleName;
  if (strrpos($symbolFile, '.pdb') !== false) {
    $symbolFile = substr($symbolFile, 0, -4);
  }
  $symbolFile .= '.sym.gz';

  print($moduleName . ' (' . $symbolFile . ')' . PHP_EOL);

  $symbols = [];

  $symbolIterator = new DirectoryIterator($root . $moduleName);
  foreach ($symbolIterator as $symbol) {
    if ($symbol->isDot() || !$symbol->isDir()) {
      continue;
    }

    $symbolName = $symbol->getFilename();

    $symbolFilePath = $root . $moduleName . '/' . $symbolName . '/' . $symbolFile; 
    $symbolFileInfo = new SplFileInfo($symbolFilePath);
    $symbolFileTime = $symbolFileInfo->getMTime();

    $symbols[$symbolFileTime] = $symbolName;

    //print('> ' . $symbolName . ' (' . $symbolFileTime . ')' . PHP_EOL);
  }

  ksort($symbols);

  $count = count($symbols);

  $symbols = array_slice($symbols, 0, -10, true);

  $cutoff = time() - (60 * 60 * 24 * 31 * 6); // Roughly 6 months.

  $deleted = 0;
  foreach ($symbols as $mtime => $symbolName) {
    if ($mtime > $cutoff) {
      break;
    }

    $symbolFileDir = $root . $moduleName . '/' . $symbolName;
    $symbolFilePath = $symbolFileDir . '/' . $symbolFile;

    //unlink($symbolFilePath);
    //rmdir($symbolFileDir);

    $deleted += 1;
  }

  print('... Deleted ' . $deleted . ' symbols (' . ($count - $deleted) . ' remaining)' . PHP_EOL);
 
  // TODO: Only run the first module for testing.
  //break;
}

