<?php

$app = require_once __DIR__ . '/app/bootstrap.php';

$out = PhutilConsole::getConsole();

$out->writeErr('Loading minidumps...');

$query = $app['db']->executeQuery('SELECT identifier, crash FROM module WHERE name = \'linux-gate.so\' AND identifier != \'000000000000000000000000000000000\' AND present = 0 GROUP BY identifier');

$out->writeErr(' done. (' . $query->rowCount() . ')' . PHP_EOL);

$bar = id(new PhutilConsoleProgressBar())->setTotal($query->rowCount());

$unknown = array();

while ($row = $query->fetch()) {
  $bar->update(1);

  $id = $row['crash'];
  $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';
  $minidump = \Filesystem::readFile($path);

  $output = array('id' => $id);

  $output['header'] = $header = unpack('A4magic/Lversion/Lstream_count/Lstream_offset', $minidump);

  $stream_offset = $header['stream_offset'];
  $stream = false;
  do {
    $output['stream'] = $stream = unpack('Ltype/Lsize/Loffset', substr($minidump, $stream_offset, 12));
    $stream_offset += 12;
  } while ($stream !== false && $stream['type'] !== 7);

  if ($stream === false) {
    throw new \RuntimeException('Missing MD_SYSTEM_INFO_STREAM');
  }

  $output['cpu'] = $cpu = unpack('A*vendor', substr($minidump, $stream['offset'] + 32, 12));

  $symbols = null;
  switch($cpu['vendor']) {
    case 'GenuineIntel':
      $symbols = "MODULE Linux x86 %s linux-gate.so\nPUBLIC 400 0 __kernel_vsyscall\nSTACK WIN 4 400 200 3 3 0 0 0 0 0 1\n";
      break;
    case 'AuthenticAMD':
      $symbols = "MODULE Linux x86 %s linux-gate.so\nPUBLIC 400 0 __kernel_vsyscall\nSTACK WIN 4 400 100 1 1 0 0 0 0 0 1\n";
      break;
  }

  if ($symbols === null) {
    $unknown[$cpu['vendor']] = true;
    continue;
  }

  $symbols = sprintf($symbols, $row['identifier']);

  $path = $app['root'] . '/symbols/public/linux-gate.so/' . $row['identifier'];

  \Filesystem::createDirectory($path, 0755, true);

  \Filesystem::writeFile($path . '/' . 'linux-gate.so.sym.gz', gzencode($symbols));

  $app['db']->executeUpdate('UPDATE module SET present = 1 WHERE name = \'linux-gate.so\' AND identifier = ?', array($row['identifier']));
}

$bar->done();

$out->writeErr('Waiting for processing lock...' . PHP_EOL);

$lock = \PhutilFileLock::newForPath($app['root'] . '/cache/process.lck');
$lock->lock(300);

try {
    $redis = new \Redis();
    $redis->pconnect('127.0.0.1', 6379, 1);

    $redis->del('throttle:cache:symbol');

    $redis->close();
} catch (\Exception $e) {}

$out->writeErr('Flushed symbol cache' . PHP_EOL);

$lock->unlock();

if (!empty($unknown)) {
  print_r($unknown);
}
