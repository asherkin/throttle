<?php

$app = require_once __DIR__ . '/app/bootstrap.php';

// SELECT crash, metadata FROM frame JOIN crash ON crash = id WHERE frame.thread = 0 AND frame = 0 AND rendered = 'server.so!CCSPhysicsPushEntities::EnsureValidPushWhileRiding(CBaseEntity*, CBaseEntity*) + 0x8b'

$out = PhutilConsole::getConsole();

$out->writeErr('Loading minidumps...');

$query = $app['db']->executeQuery('SELECT id, metadata FROM crash');

$out->writeErr(' done.' . PHP_EOL);

$maps = array();

$bar = id(new PhutilConsoleProgressBar())->setTotal($query->rowCount());

while ($row = $query->fetch()) {
  $bar->update(1);

  $metadata = json_decode($row['metadata'], true);

  if (!$metadata || empty($metadata['ExtensionVersion']) || version_compare($metadata['ExtensionVersion'], '2.2.1', '<')) {
    continue;
  }

  $id = $row['id'];
  $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.meta.txt.gz';

  try {
    $metadata = gzdecode(\Filesystem::readFile($path));
  } catch(Exception $e) {
    continue;
  }

  $ret = preg_match('/(?<=-------- CONFIG BEGIN --------)[^\\x00]+(?=-------- CONFIG END --------)/i', $metadata, $metadata);

  if ($ret !== 1) {
    continue;
  }

  $metadata = phutil_split_lines(trim($metadata[0]), false);

  foreach ($metadata as $line) {
    list($key, $value) = explode('=', $line, 2);

    if ($key !== 'Map') {
      continue;
    }

    if (!array_key_exists($value, $maps)) {
      $maps[$value] = 1;
    } else {
      $maps[$value] += 1;
    }

    break;
  }
}

$bar->done();

arsort($maps);

print_r($maps);

