#!/usr/bin/env php
<?php

$_ = null;
$output = exec('./bin/carburetor ./app/carburetor-config-no-symbols.json '.$argv[1].' 2>/dev/null', $_, $ret);
if ($ret !== 0) {
    exit($ret);
}

$output = json_decode($output, true);
if ($output === false) {
    exit(1);
}

//print_r($output);

$rq = isset($output['requesting_thread']) ? $output['requesting_thread'] : 0;
$thread = isset($output['threads']) ? (isset($output['threads'][$rq]) ? $output['threads'][$rq] : null) : null;

if ($thread === null) {
    exit(1);
}

$stack = array_reduce(array_slice($thread, 0, 10), function($s, $t) {
    if (!isset($t['stack'])) return $s;
    return $s . base64_decode($t['stack']);
}, '');

$ret = preg_match('/Host_Error: ([^\\x00]*)[\\x00]/', $stack, $matches);

if ($ret === false) {
    exit(1);
}

if ($ret === 0) {
    print($argv[1].': [no error]'.PHP_EOL);
    exit(0);
}

$error = trim($matches[1]);
print($argv[1].': '.$error.PHP_EOL);
exit(0);

