<?php

namespace Throttle;

use Silex\Application;

class Crash
{
    private static function generateId($app)
    {
        for ($i = 0; $i < 10; $i++) {
            $id = \Filesystem::readRandomCharacters(12);
            $path = $app['root'] . '/dumps/' . substr($id, 0, 2);

            if (\Filesystem::pathExists($path . '/' . $id . '.dmp')) {
                continue;
            }

            return array($id, $path);
        }

        throw new \Exception('MINIDUMP COLLISION');
    }

    private static function canUserManage($app, $crash)
    {
        if (!$app['user'] || $app['user']['admin']) {
            // Hacky, but execute a query to check if the crash doesn't exist at all.
            $query = $app['db']->executeQuery('SELECT 1 FROM crash WHERE crash.id = ?', [$crash])->fetchColumn(0);

            return ($query === false) ? null : !!$app['user']['admin'];
        }

        $query = $app['db']->executeQuery('SELECT COALESCE(crash.owner = ? OR EXISTS (SELECT TRUE FROM share WHERE share.owner = crash.owner AND share.user = ?), 0) AS manage FROM crash WHERE crash.id = ?', [$app['user']['id'], $app['user']['id'], $crash])->fetchColumn(0);

        if ($query === false) {
            return null;
        }

        if ($query === '1') {
            return true;
        }

        if ($query === '0') {
            return false;
        }

        throw new \Exception('Bad query return in '.__FUNCTION__);
    }

    public static function parsePresubmitSignature($signature)
    {
        $signature = array_reverse(explode('|', $signature));
        $version = (int)array_pop($signature);
        if ($version < 0 || $version > 2) {
            throw new \Exception('bad version');
        }

        $timestamp = time();
        $platform = '';
        $architecture = 'x86';
        if ($version > 1) {
            $timestamp = (int)array_pop($signature);
            $platform = array_pop($signature);
            $architecture = array_pop($signature);
        }

        $crashed = (int)array_pop($signature);
        $crash_reason = array_pop($signature);
        $crash_address = intval(array_pop($signature), 16);
        $requesting_thread = (int)array_pop($signature);

        $modules = [];
        $frames = [];

        while (!empty($signature)) {
            $type = array_pop($signature);
            switch ($type) {
                case 'M':
                    $file = array_pop($signature);
                    if (!strlen($platform)) {
                        switch (pathinfo($file, PATHINFO_EXTENSION)) {
                            case 'pdb':
                                $platform = 'windows';
                                break;
                            case 'dylib':
                                $platform = 'mac';
                                break;
                            case 'so':
                                $platform = 'linux';
                                break;
                        }
                    }
                    $modules[] = (object)[
                        'file' => $file,
                        'identifier' => array_pop($signature),
                    ];
                    break;
                case 'F':
                    $frames[] = (object)[
                        'module' => (int)array_pop($signature),
                        'offset' => intval(array_pop($signature), 16),
                    ];
                    break;
                default:
                    throw new \Exception('unknown field '.$type);
            }
        }

        return (object)compact('timestamp', 'platform', 'architecture', 'crashed', 'crash_reason', 'crash_address', 'requesting_thread', 'modules', 'frames');
    }

    public function presubmit(Application $app, $signature)
    {
        //$app['monolog']->warning('Presubmit: '.$signature);

        try {
            $signature = self::parsePresubmitSignature($signature);
        } catch (\Exception $e) {
            $app['monolog']->warning('Error parsing presubmit: '.$signature, [$e]);

            return 'E|'.$e->getMessage();
        }

        $app['redis']->hIncrBy('throttle:stats', 'crashes:presubmitted', 1);

        // TODO: Determine whether we want the crash dump...
        $return = 'Y|';
        foreach ($signature->modules as $module) {
            // TODO: N query problem...
            $exists = $app['db']->executeQuery('SELECT TRUE FROM module WHERE name = ? AND identifier = ? AND present = 1 LIMIT 1', [$module->file, $module->identifier])->fetchColumn(0);
            $return .= ($exists === false) ? 'Y' : 'N';
        }

        // Stick a random presubmit token on the end for testing.
        $return .= '|'.md5($return);

        return $return;
    }

    public function submit(Application $app)
    {
        //TODO
        //return $app->abort(503);
        //return 'Sorry, crash submission is currently disabled';

        $presubmit = $app['request']->get('CrashSignature');
        if ($presubmit !== null) {
            return $this->presubmit($app, $presubmit);
        }

        $app['redis']->hIncrBy('throttle:stats', 'crashes:submitted', 1);

        $minidump = $app['request']->files->get('upload_file_minidump');

        if ($minidump === null || !$minidump->isValid() || $minidump->getClientSize() <= 0) {
            $app['redis']->hIncrBy('throttle:stats', 'crashes:rejected:no-minidump', 1);

            return $app['twig']->render('submit-empty.txt.twig');
        }

        $app['redis']->hIncrBy('throttle:stats', 'crashes:submitted:bytes', $minidump->getClientSize());

        list($id, $path) = self::generateId($app);

        $ip = $app['request']->getClientIp();

        $owner = $app['request']->request->get('UserID');
        if ($owner !== null) {
            $app['request']->request->remove('UserID');

            if ($owner == 0) {
                $owner = null;
            } else if (stripos($owner, 'STEAM_') === 0) {
                $owner = explode(':', $owner);
                $owner = ($owner[2] << 1) | $owner[1];
                $owner = gmp_add('76561197960265728', $owner);
            } /* else if (gmp_cmp($owner, '0xFFFFFFFF') < 0) {
                $owner = gmp_add('76561197960265728', $owner);
            } */ else if (gmp_cmp(gmp_and($owner, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
                $app['monolog']->warning('Bad owner provided in submit', array('id' => $id, 'owner' => $owner));
                $owner = null;
            }

            if ($owner !== null) {
                $app['db']->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($owner));
            }
        }

        $server = $app['request']->request->get('ServerID');
        if ($server !== null) {
            $app['request']->request->remove('ServerID');

            // TODO: Validate ID, then insert, just like above.
        }

        $count = 0;

        if ($owner !== null) {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash JOIN crashnotice ON crash = id AND notice LIKE \'nosteam-%\' WHERE owner = ? AND ip = INET6_ATON(?) AND processed = 1 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH)', array($owner, $ip))->fetchColumn(0);
        } else {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash JOIN crashnotice ON crash = id AND notice LIKE \'nosteam-%\' WHERE owner IS NULL AND ip = INET6_ATON(?) AND processed = 1 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH)', array($ip))->fetchColumn(0);
        }

        if ($count > 0) {
            $app['redis']->hIncrBy('throttle:stats', 'crashes:rejected:no-steam', 1);

            return $app['twig']->render('submit-nosteam.txt.twig');
        }

        if ($owner !== null) {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash WHERE owner = ? AND ip = INET6_ATON(?) AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)', array($owner, $ip))->fetchColumn(0);
        } else {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash WHERE owner IS NULL AND ip = INET6_ATON(?) AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)', array($ip))->fetchColumn(0);
        }

        if ($count > 12) {
            $app['redis']->hIncrBy('throttle:stats', 'crashes:rejected:rate-limit', 1);

            return $app['twig']->render('submit-reject.txt.twig');
        }

        $metadata = $app['request']->request->all();

        $raw_metadata = null;
        $metadata_file = $app['request']->files->get('upload_file_metadata');
        if ($metadata_file !== null && $metadata_file->isValid() && $metadata_file->getClientSize() > 0) {
            $raw_metadata = \Filesystem::readFile($metadata_file->getRealPath());

            $has_config = preg_match('/(?<=-------- CONFIG BEGIN --------)[^\\x00]+(?=-------- CONFIG END --------)/i', $raw_metadata, $metadata_config);
            if ($has_config === 1) {
                $metadata_config = trim($metadata_config[0]);
                $metadata_config = phutil_split_lines($metadata_config, false);

                // Merge with the existing metadata, overwrite existing.
                foreach ($metadata_config as $line) {
                    list($key, $value) = array_pad(explode('=', $line, 2), 2, '');

                    $key = trim($key);
                    $value = trim($value);

                    if (strlen($value)) {
                        $metadata[$key] = $value;
                    }
                }
            }

            $has_console = preg_match('/(?<=-------- CONSOLE HISTORY BEGIN --------)[^\\x00]+(?=-------- CONSOLE HISTORY END --------)/i', $raw_metadata, $metadata_console);
            if ($has_console === 1 && strlen(trim($metadata_console[0]))) {
                $metadata['HasConsoleLog'] = true;
            }
        }

        $command_line = null;
        if (isset($metadata['CommandLine'])) {
            $command_line = $metadata['CommandLine'];
            unset($metadata['CommandLine']);
        }

        // Strip any presubmit token, until we're ready to do something with them
        if (isset($metadata['PresubmitToken'])) {
            unset($metadata['PresubmitToken']);
        }

        $metadata = json_encode($metadata, JSON_FORCE_OBJECT|JSON_UNESCAPED_SLASHES);

        $app['db']->executeUpdate('INSERT INTO crash (id, timestamp, ip, owner, metadata, cmdline) VALUES (?, NOW(), INET6_ATON(?), ?, ?, ?)', array($id, $ip, $owner, $metadata, $command_line));

        // Move after it's in the DB, to avoid a race condition with the cleanup code.
        \Filesystem::createDirectory($path, 0755, true);
        $minidump->move($path, $id . '.dmp');

        if ($raw_metadata) {
            $metapath = $path . '/' . $id . '.meta.txt.gz';
            \Filesystem::writeFile($metapath, gzencode($raw_metadata));
        }

        $app['redis']->hIncrBy('throttle:stats', 'crashes:accepted', 1);

/*
        try {
            $app['queue']->putInTube('carburetor', json_encode(array(
                'id' => $id,
                'owner' => $owner,
                'ip' => $ip,
            )));
        } catch (\Exception $e) {}
*/

        // Special code for handling breakpad-uploaded minidumps.
        // FIXME: This is mainly a hack for testing Electron.
        if ($app['request']->request->get('prod')) {
            $bid = '1000'; // First 2 bits specify UUID variant
            $map = array_merge(range('a', 'z'), range('2', '7'));
            for ($i = 0; $i < 12; $i++) {
                $bid .= sprintf('%05b', array_search($id[$i], $map));
            }
            $bid = str_split($bid, 8);

            $uuid = 'bee0cafe-0000-4000-'; // 4 = UUID version
            for ($i = 0; $i < 8; $i++) {
                $uuid .= sprintf('%02x', bindec($bid[$i]));
                if ($i === 1) $uuid .= '-';
            }

            return $uuid;
        }

        return $app['twig']->render('submit.txt.twig', array(
            'id' => $id,
        ));
    }

    public function details(Application $app, $id)
    {
        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            if ($app['session']->getFlashBag()->get('internal')) {
                $app['session']->getFlashBag()->add('error_crash', 'That Crash ID does not exist.');

                return $app->redirect($app['url_generator']->generate('index'));
            }
    
            return $app->abort(404);
        }

        $crash = $app['db']->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) AS timestamp, INET6_NTOA(ip) AS ip, owner, metadata, cmdline, thread, processed, failed, stackhash, UNIX_TIMESTAMP(crash.lastview) AS lastview, user.name FROM crash LEFT JOIN user ON user.id = crash.owner WHERE crash.id = ?', array($id))->fetch();

        if ($crash['lastview'] === null || (time() - $crash['lastview']) > (60 * 60 * 24)) {
            $app['db']->executeUpdate('UPDATE crash SET lastview = NOW() WHERE id = ?', array($id));
        }

        if ($crash['thread'] == -1) {
            $crash['thread'] = 0;
        }

        $crash['metadata'] = json_decode($crash['metadata'], true);

        if (isset($crash['metadata']['HasConsoleLog'])) {
            $crash['has_console_log'] = $crash['metadata']['HasConsoleLog'];
            unset($crash['metadata']['HasConsoleLog']);
        } else {
            $crash['has_console_log'] = false;
        }

        if (isset($crash['metadata']['ExtensionBuild'])) {
            unset($crash['metadata']['ExtensionBuild']);
        }

        ksort($crash['metadata']);

        $notices = $app['db']->executeQuery('SELECT severity, text FROM crashnotice JOIN notice ON notice.id = crashnotice.notice WHERE crash = ?', array($id))->fetchAll();
        $stack = $app['db']->executeQuery('SELECT frame, rendered, url FROM frame WHERE crash = ? AND thread = ? ORDER BY frame', array($id, $crash['thread']))->fetchAll();
        $modules = $app['db']->executeQuery('SELECT name, identifier, processed, present, HEX(base) AS base FROM module WHERE crash = ? ORDER BY name', array($id))->fetchAll();
        $stats = $app['db']->executeQuery('SELECT COUNT(DISTINCT crash.owner) AS owners, COUNT(DISTINCT crash.ip) AS ips, COUNT(*) AS crashes FROM crash, (SELECT owner, stackhash FROM crash WHERE id = ?) AS this WHERE this.stackhash = crash.stackhash', array($id))->fetch();

        return $app['twig']->render('details.html.twig', array(
            'crash' => $crash,
            'can_manage' => $can_manage,
            'notices' => $notices,
            'stack' => $stack,
            'modules' => $modules,
            'stats' => $stats,
            'outdated' => ($app['config']['accelerator'] ? (isset($crash['metadata']['ExtensionVersion']) ? version_compare($crash['metadata']['ExtensionVersion'], $app['config']['accelerator'], '<') : true) : false),
            'has_error_string' => (isset($stack[0]['rendered']) ? (preg_match('/^engine(_srv)?\\.so!Sys_Error(_Internal)?\\(/', $stack[0]['rendered']) === 1) : false),
        ));
    }

    public function download(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!\Filesystem::pathExists($path)) {
            $app->abort(404);
        }

        return $app->sendFile($path)->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'crash_' . $id . '.dmp');
    }

    public function view(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        return $app['twig']->render('view.html.twig', array('id' => $id));
    }

    public function logs(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.txt';

        $logs = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $logs = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $logs = \Filesystem::readFile($path);
        }

        return $app['twig']->render('logs.html.twig', array('id' => $id, 'logs' => $logs));
    }

    public function metadata(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.meta.txt';

        $logs = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $logs = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $logs = \Filesystem::readFile($path);
        }

        return $app['twig']->render('logs.html.twig', array('id' => $id, 'logs' => $logs));
    }

    public function console(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.meta.txt';

        $metadata = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $metadata = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $metadata = \Filesystem::readFile($path);
        }

        // Extract the console output from the full metadata.
        $ret = preg_match('/(?<=-------- CONSOLE HISTORY BEGIN --------)[^\\x00]+(?=-------- CONSOLE HISTORY END --------)/i', $metadata, $console);

        if ($ret !== 1) {
            $app->abort(404);
        }

        $console = $console[0]; // Get the console output.
        $console = trim($console); // Remove the extra newlines from the markers.
        $console = str_replace("\r\n", PHP_EOL, $console); // Normalize line endings.

        // Split the console output into individual prints.
        preg_match_all('/(\\d+)\\((\\d+\\.?\\d*)\\):  ([^\\x00]*?)(?=(?:\\d+\\(\\d+\\.\\d+\\):  )|$)/', $console, $console, PREG_SET_ORDER);

        $console = array_reverse($console); // Flip them back into chronological order.

        return $app['twig']->render('console.html.twig', array('id' => $id, 'console' => $console));
    }

    public function error(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!\Filesystem::pathExists($path)) {
            $app->abort(404);
        }

        $minidump = \Filesystem::readFile($path);

        $output = array();

        $output['header'] = $header = unpack('A4magic/Lversion/Lstream_count/Lstream_offset', $minidump);

        $stream_offset = $header['stream_offset'];
        $stream = false;
        do {
            $output['stream'] = $stream = unpack('Ltype/Lsize/Loffset', substr($minidump, $stream_offset, 16));
            $stream_offset += 16;
        } while ($stream !== false && $stream['type'] !== 3);

        if ($stream === false) {
            throw new \RuntimeException('Missing MD_THREAD_LIST_STREAM');
        }

        $thread = $app['db']->executeQuery('SELECT thread FROM crash WHERE id = ? AND processed = 1 LIMIT 1', array($id))->fetchColumn(0);
        $output['thread'] = $thread = unpack('Lthread_id/Lsuspend_count/Lpriority_class/Lpriority/L2teb/L2stack_start/Lstack_size/Lstack_offset/Lcontext_size/Lcontext_offset', substr($minidump, $stream['offset'] + 4 + ($thread * 48), 48));

        $output['context_flags'] = $context_flags = unpack('Lflags', substr($minidump, $thread['context_offset'], 4));

        if (($context_flags['flags'] & 0x10000) === 0) {
            throw new \RuntimeException('Bad context flags.');
        }

        function get_register_offset($minidump, $stack_start, $register_offset) {
            $context_register = unpack('Lregister', substr($minidump, $register_offset, 4));
            return (int)bcsub(sprintf('%u', $context_register['register']), sprintf('%u', $stack_start));
        }

        $output['register_esp'] = $register_esp = get_register_offset($minidump, $thread['stack_start1'], $thread['context_offset'] + 196);
        $output['register_ebp'] = $register_ebp = get_register_offset($minidump, $thread['stack_start1'], $thread['context_offset'] + 180);

        $error_offset = 0;
        for ($i = 0; $i < 6; $i++) {
            $output['register_offset_'.$i] = $register_offset = get_register_offset($minidump, $thread['stack_start1'], $thread['context_offset'] + 156 + ($i * 4));
            if ($register_offset >= $register_esp && $register_offset <= $register_ebp) {
                $output['error_offset'] = $error_offset = $register_offset;
                break;
            }
        }

        if ($error_offset === 0) {
            return $app->json(array('string' => 'Failed to extract error message.'));
        }

        $output['string_start'] = $string_start = $thread['stack_offset'] + $error_offset;
        $string_length = 0;

        while (ord($minidump[$string_start + $string_length]) != 0 && $string_length < 256) {
            $string_length++;
        }

        $output['string_length'] = $string_length;

        $output['error_string'] = $error_string = substr($minidump, $string_start, $string_length);

        // Remove non-ASCII chars, this needs a cleanup, but just fix the errors while encoding UTF-8 for now.
        $error_string = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '?', $error_string);

        return $app->json(array('string' => $error_string));
    }

    public function carburetor(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        return $app['twig']->render('carburetor.html.twig', array(
            'id' => $id,
            'symbols' => $app['request']->get('symbols', null),
        ));
    }

    public function carburetor_data(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        $config = $app['root'].'/app/carburetor-config.json';
        if ($app['request']->get('symbols', null) === 'no') {
            $config = $app['root'].'/app/carburetor-config-no-symbols.json';
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!\Filesystem::pathExists($path)) {
            $app->abort(404);
        }

        set_time_limit(120);

        list($stdout, $stderr) = execx($app['root'].'/bin/carburetor %s %s', $config, $path);

        return new \Symfony\Component\HttpFoundation\Response($stdout, 200, array(
            'Content-Type' => 'application/json',
        ));
    }

    public function reprocess(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        if (!$app['user']['admin']) {
            $app->abort(403);
        }

        $app['db']->transactional(function($db) use ($id) {
            $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
            $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));
            $db->executeUpdate('DELETE FROM crashnotice WHERE crash = ?', array($id));

            $db->executeUpdate('UPDATE crash SET thread = NULL, processed = FALSE, failed = FALSE, stackhash = NULL WHERE id = ?', array($id));
        });

        $return = $app['request']->get('return', null);
        if (!$return) {
            $return = $app['url_generator']->generate('dashboard');
        }

        return $app->redirect($return);
    }

    public function delete(Application $app, $id)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $can_manage = self::canUserManage($app, $id);
        if ($can_manage === null) {
            $app->abort(404);
        }

        if (!$can_manage) {
            $app->abort(403);
        }

        $app['db']->executeUpdate('DELETE FROM crash WHERE id = ?', array($id));

        $return = $app['request']->get('return', null);
        if (!$return) {
            $return = $app['url_generator']->generate('dashboard');
        }

        return $app->redirect($return);
    }

    public function dashboard(Application $app, $offset)
    {
        if ($app['user'] === null) {
            $app->abort(401);
        }

        $shared = $app['db']->executeQuery('SELECT share.owner AS id, user.name, user.avatar FROM share LEFT JOIN user ON share.owner = user.id WHERE share.user = ? AND accepted IS NOT NULL ORDER BY accepted ASC', array($app['user']['id']))->fetchAll();

        array_unshift($shared, [
            'id' => $app['user']['id'],
            'name' => $app['user']['name'],
            'avatar' => $app['user']['avatar'],
        ]);

        $userid = $app['request']->get('user', null);

        $allowed = null;
        if (!$app['user']['admin']) {
            foreach ($shared as $user) {
                $allowed[] = $user['id'];
            }

            if ($userid !== null && !in_array($userid, $allowed, true)) {
                $app->abort(403);
            }
        }

        $where = '';
        $params = [];
        $types = [];

        if ($offset !== null || $userid !== null || $allowed !== null) {
            $where .= 'WHERE ';


            if ($userid !== null || $allowed !== null) {
                if ($userid !== null) {
                    $where .= 'owner = ?';
                    $params[] = $userid;
                    $types[] = \PDO::PARAM_INT;
                } else if ($allowed !== null) {
                    $where .= 'owner IN (?)';
                    $params[] = $allowed;
                    $types[] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
                }

                if ($offset !== null) {
                    $where .= ' AND ';
                }
            }

            if ($offset !== null) {
                $where .= 'timestamp < FROM_UNIXTIME(?)';
                $params[] = $offset;
                $types[] = \PDO::PARAM_INT;
            }
        }

        $crashes = $app['db']->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, crash.owner, crash.cmdline, crash.processed, crash.failed, user.name, user.avatar, frame.module, frame.rendered, frame2.module as module2, frame2.rendered AS rendered2, (SELECT CONCAT(COUNT(*), \'-\', MIN(notice.severity)) FROM crashnotice JOIN notice ON crashnotice.notice = notice.id WHERE crashnotice.crash = crash.id) AS notice FROM crash LEFT JOIN user ON crash.owner = user.id LEFT JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 LEFT JOIN frame AS frame2 ON crash.id = frame2.crash AND crash.thread = frame2.thread AND frame2.frame = 1 ' . $where . ' ORDER BY crash.timestamp DESC LIMIT 20', $params, $types)->fetchAll();

        return $app['twig']->render('dashboard.html.twig', array(
            'userid' => $userid,
            'shared' => $shared,
            'offset' => $offset,
            'crashes' => $crashes,
        ));
    }
}

