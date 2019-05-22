<?php

$symbols = [];
$symbolsFile = file('all_symbols.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($symbolsFile as $line) {
    $line = preg_split('#/#', $line, null, PREG_SPLIT_NO_EMPTY);

    if (count($line) !== 4) {
      error_log('Failed to parse all_symbols, too many: ' . print_r($line, true));
      exit();
    }

    list(, $store, $name, $identifier) = $line;

    if (!isset($symbols[$name])) {
        $symbols[$name] = [];
    }

    if (!isset($symbols[$name][$identifier])) {
        $symbols[$name][$identifier] = [];
    }

    $symbols[$name][$identifier][] = $store;
}

foreach ($symbols as $name => $identifiers) {
    foreach ($identifiers as $identifier => $stores) {
        if (count($stores) <= 1) {
            unset($symbols[$name][$identifier]);
        }
    }

    if (count($symbols[$name]) <= 0) {
        unset($symbols[$name]);
    }
}

//print_r($symbols);

foreach ($symbols as $name => $identifiers) {
    foreach ($identifiers as $identifier => $stores) {
        sort($stores);
        if (count($stores) !== 2) {
            throw new Exception('not implemented');
        }

        $file = $name;
        if (strrpos($file, '.pdb') !== false) {
            $file = substr($file, 0, -4);
        }
        $file .= '.sym';

        $good = null;
        $bad = null;
        if ($stores[0] === 'public') {
            // Any store wins over public
            $good = $stores[1];
            $bad = $stores[0];
        } else if ($stores[0] === 'microsoft' && $stores[1] === 'mozilla') {
            // Official MS symbols win over Moz's
            $good = $stores[0];
            $bad = $stores[1];
        } else {
            throw new Exception('unhandled store pairing');
        }

        print("Taking symbols for $name/$identifier from $good, removing $bad\n");

        if (!file_exists("symbols/$bad/$name/$identifier")) {
            print("  already removed?\n");
            continue;
        }

        if (file_exists("symbols/$bad/$name/$identifier/$file.gz")) {
            unlink("symbols/$bad/$name/$identifier/$file.gz");
        }

        rmdir("symbols/$bad/$name/$identifier");

        if (file_exists("cache/symbols/$name/$identifier")) {
            print("  also removing from cache\n");

            if (file_exists("cache/symbols/$name/$identifier/$file")) {
                unlink("cache/symbols/$name/$identifier/$file");
            }

            rmdir("cache/symbols/$name/$identifier");
        }
    }
}
