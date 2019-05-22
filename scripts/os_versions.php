<?php

$app = require_once __DIR__ . '/app/bootstrap.php';

set_error_handler(function($severity, $message, $file, $line) {
  if (!(error_reporting() & $severity)) {
    return false;
  }

  throw new ErrorException($message, 0, $severity, $file, $line);
});

$out = PhutilConsole::getConsole();

$out->writeErr('Loading minidumps...');

$query = $app['db']->executeQuery('SELECT id FROM crash WHERE processed = 1 AND failed = 0');

$out->writeErr(' done. (' . $query->rowCount() . ')' . PHP_EOL);

$bar = id(new PhutilConsoleProgressBar())->setTotal($query->rowCount());

$versions = array();

while ($row = $query->fetch()) {
  $bar->update(1);

  try {
    $id = $row['id'];
    $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';
    $minidump = \Filesystem::readFile($path);

    $output = array('id' => $id);

    $output['header'] = $header = unpack('A4magic/Lversion/Lstream_count/Lstream_offset', $minidump);

    $stream_offset = $header['stream_offset'];
    $stream = false;
    do {
      $output['stream'] = $stream = unpack('Ltype/Lsize/Loffset', $minidump, $stream_offset);
      $stream_offset += 12;
    } while ($stream !== false && $stream['type'] !== 7);

    if ($stream === false) {
      throw new \RuntimeException('Missing MD_SYSTEM_INFO_STREAM');
    }
   
    $output['system'] = $system = unpack('vprocessor_architecture/vprocessor_level/vprocessor_revision/Cnumber_of_processors/Cproduct_type/Vmajor_version/Vminor_version/Vbuild_number/Vplatform_id/Vcsd_version_rva/vsuite_mask/vreserved2', $minidump, $stream['offset']);
    $output['version_length'] = $version_length = unpack('V', $minidump, $system['csd_version_rva'])[1];
    $output['version_raw'] = $version_raw = unpack('a'.$version_length, $minidump, $system['csd_version_rva'] + 4)[1];
    $output['version'] = $version = iconv('utf-16', 'utf-8', $version_raw);

    $string = '';

    // if ($system['platform_id'] >= 0x1000) continue;
    // if ($system['major_version'] <= 5) { print(PHP_EOL); var_dump($id); }

    switch ($system['platform_id']) {
    case 2:
      $string .= 'Windows NT';
      break;
    case 0x8101:
      $string .= 'macOS';
      break;
    case 0x8201:
      $string .= 'Linux';
      break;
    default:
      $string .= sprintf('[Platform 0x%X]', $system['platform_id']);
    }

    $string .= sprintf(' %d.%d.%d', $system['major_version'], $system['minor_version'], $system['build_number']);

    if (strlen($version)) {
      $string .= ' '.$version;
    }

    if (isset($versions[$string])) {
      $versions[$string]++;
    } else {
      $versions[$string] = 1;
    }
  } catch (Throwable $e) {
    // print(PHP_EOL); var_dump($e->getMessage());
    continue;
  }
}

$bar->done();

arsort($versions);
print_r($versions);
