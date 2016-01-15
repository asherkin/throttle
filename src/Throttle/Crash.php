<?php

namespace Throttle;

use Silex\Application;

class Crash
{
    public function submit(Application $app)
    {
        //TODO
        //return $app->abort(503);

        $minidump = $app['request']->files->get('upload_file_minidump');

        if ($minidump === null || !$minidump->isValid() || $minidump->getClientSize() <= 0) {
            return $app->abort(400);
        }

        $id = \Filesystem::readRandomCharacters(12);

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2);
        if (file_exists($path . '/' . $id . '.dmp')) {
            throw new \Exception('MINIDUMP COLLISION');
        }

        $owner = $app['request']->request->get('UserID');
        $server = null;

        if ($owner !== null) {
            $app['request']->request->remove('UserID');

            if ($owner == 0) {
                $owner = null;
            } else if (stripos($owner, 'STEAM_') === 0) {
                $owner = explode(':', $owner);
                $owner = ($owner[2] << 1) | $owner[1];
                $owner = gmp_add('76561197960265728', $owner);
            }

            if ($owner !== null) {
                //TODO As we don't need to query this until servers and permissions are added, just blindly inserting saves us a query.
                $app['db']->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($owner));
                $app['db']->executeUpdate('INSERT IGNORE INTO server (owner) VALUES (?)', array($owner));
                $server = '';
            }
        }

        $ip = $app['request']->getClientIp();

        $count = 0;

        if ($owner !== null) {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash JOIN crashnotice ON crash = id AND notice LIKE \'nosteam-%\' WHERE owner = ? AND ip = INET_ATON(?) AND processed = 1 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH)', array($owner, $ip))->fetchColumn(0);
        } else {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash JOIN crashnotice ON crash = id AND notice LIKE \'nosteam-%\' WHERE owner IS NULL AND ip = INET_ATON(?) AND processed = 1 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH)', array($ip))->fetchColumn(0);
        }

        if ($count > 0) {
            return $app['twig']->render('submit-nosteam.txt.twig');
        }

        if ($owner !== null) {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash WHERE owner = ? AND ip = INET_ATON(?) AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)', array($owner, $ip))->fetchColumn(0);
        } else {
            $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash WHERE owner IS NULL AND ip = INET_ATON(?) AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)', array($ip))->fetchColumn(0);
        }

        if ($count > 6) {
            return $app['twig']->render('submit-reject.txt.twig');
        }

        $metadata = json_encode($app['request']->request->all());

        $app['db']->executeUpdate('INSERT INTO crash (id, timestamp, ip, owner, metadata, server) VALUES (?, NOW(), INET_ATON(?), ?, ?, ?)', array($id, $ip, $owner, $metadata, $server));

	// Move after it's in the DB, to avoid a race condition with the cleanup code.
        \Filesystem::createDirectory($path, 0755, true);
        $minidump->move($path, $id . '.dmp');

        $metadata = $app['request']->files->get('upload_file_metadata');
    
        if ($metadata !== null && $metadata->isValid() && $metadata->getClientSize() > 0) {
            $metadata->move($path, $id . '.meta.txt');

            $metapath = $path . '/' . $id . '.meta.txt';
            \Filesystem::writeFile($metapath . '.gz', gzencode(\Filesystem::readFile($metapath)));
            \Filesystem::remove($metapath);
        }

        try {
            $app['queue']->putInTube('carburetor', json_encode(array(
                'id' => $id,
                'owner' => $owner,
                'ip' => $ip,
            )));
        } catch (\Exception $e) {}

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
        $crash = $app['db']->executeQuery('SELECT id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, owner, metadata, cmdline, thread, processed, failed FROM crash WHERE id = ?', array($id))->fetch();

        if (empty($crash)) {
            if ($app['session']->getFlashBag()->get('internal')) {
                $app['session']->getFlashBag()->add('error_crash', 'Invalid Crash ID');

                return $app->redirect($app['url_generator']->generate('index'));
            } else {
                return $app->abort(404);
            }
        }

        $crash['metadata'] = json_decode($crash['metadata'], true);

        $notices = $app['db']->executeQuery('SELECT severity, text FROM crashnotice JOIN notice ON notice.id = crashnotice.notice WHERE crash = ?', array($id))->fetchAll();
        $stack = $app['db']->executeQuery('SELECT frame, rendered, url FROM frame WHERE crash = ? AND thread = ? ORDER BY frame', array($id, $crash['thread']))->fetchAll();
        $modules = $app['db']->executeQuery('SELECT name, identifier, processed, present, HEX(base) AS base FROM module WHERE crash = ? ORDER BY name', array($id))->fetchAll();

        return $app['twig']->render('details.html.twig', array(
            'crash' => $crash,
            'notices' => $notices,
            'stack' => $stack,
            'modules' => $modules,
            'outdated' => (isset($crash['metadata']['ExtensionVersion']) ? version_compare($crash['metadata']['ExtensionVersion'], '2.2.0', '<') : true),
            'has_error_string' => (isset($stack[0]['rendered']) ? (preg_match('/^(engine(_srv)?\\.so!Sys_Error(_Internal)?\\(|libtier0\\.so!Plat_ExitProcess|KERNELBASE\\.dll!RaiseException)/', $stack[0]['rendered']) === 1) : false),
        ));
    }

    public function download(Application $app, $id)
    {
        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!file_exists($path)) {
            $app->abort(404);
        }

        $user = $app['session']->get('user');
        $owner = $app['db']->executeQuery('SELECT owner FROM crash WHERE id = ? LIMIT 1', array($id))->fetchColumn(0);

        if ($user === null || (!$user['admin'] && $user['id'] !== $owner)) {
            $app->abort(403);
        }

        return $app->sendFile($path)->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'crash_' . $id . '.dmp');
    }

    public function view(Application $app, $id)
    {
        $user = $app['session']->get('user');
        $owner = $app['db']->executeQuery('SELECT owner FROM crash WHERE id = ? LIMIT 1', array($id))->fetch();

        if ($owner === false) {
            $app->abort(404);
        }

        if ($user === null || (!$user['admin'] && $user['id'] !== $owner['owner'])) {
            $app->abort(403);
        }

        return $app['twig']->render('view.html.twig');
    }

    public function logs(Application $app, $id)
    {
        $user = $app['session']->get('user');
        $owner = $app['db']->executeQuery('SELECT owner FROM crash WHERE id = ? LIMIT 1', array($id))->fetch();

        if ($owner === false) {
            $app->abort(404);
        }

        if ($user === null || (!$user['admin'] && $user['id'] !== $owner['owner'])) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.txt';

        $logs = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $logs = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $logs = \Filesystem::readFile($path);
        }

        return $app['twig']->render('logs.html.twig', array('logs' => $logs));
    }

    public function metadata(Application $app, $id)
    {
        $user = $app['session']->get('user');
        $owner = $app['db']->executeQuery('SELECT owner FROM crash WHERE id = ? LIMIT 1', array($id))->fetch();

        if ($owner === false) {
            $app->abort(404);
        }

        if ($user === null || (!$user['admin'] && $user['id'] !== $owner['owner'])) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.meta.txt';

        $logs = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $logs = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $logs = \Filesystem::readFile($path);
        }

        return $app['twig']->render('logs.html.twig', array('logs' => $logs));
    }

    public function console(Application $app, $id)
    {
        $user = $app['session']->get('user');
        $owner = $app['db']->executeQuery('SELECT owner FROM crash WHERE id = ? LIMIT 1', array($id))->fetch();

        if ($owner === false) {
            $app->abort(404);
        }

        if ($user === null || (!$user['admin'] && $user['id'] !== $owner['owner'])) {
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
        preg_match_all('/(\\d+)\\((\\d+\\.?\\d*)\\):  ([^\\x00]*?)(?=(?:\\d+\\(\\d+\\.\\d+\\):  )|$)/', $console, $console);

        $console = $console[3]; // Get just the text output.
        $console[] = array_pop($console) . PHP_EOL; // Add the missing newline to the last entry.
        $console = array_reverse($console); // Flip them back into the correct order.
        $console = implode('', $console); // Join them together again into one string.

        return $app['twig']->render('logs.html.twig', array('logs' => $console));
    }

    public function error(Application $app, $id)
    {
        $query = $app['db']->executeQuery('SELECT owner, thread FROM crash WHERE id = ? AND processed = 1 LIMIT 1', array($id));

        $thread = $query->fetchColumn(1);
        if ($thread === false) {
            $app->abort(404);
        }

        $user = $app['session']->get('user');
        $owner = $query->fetchColumn(0);
        if ($user === null || (!$user['admin'] && $user['id'] !== $owner)) {
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

        $string = '';
        $strings = array();
        $min_string_len = 40;

        for ($i = 0; $i < $thread['stack_size']; $i++) {
            $char = $minidump[$thread['stack_offset'] + $register_esp + $i];
            $charnum = ord($char);

            if ($charnum === 9 || $charnum === 10 || $charnum === 13 || ($charnum >= 32 && $charnum < 127)) {
                $string .= $char;
            } else {
                if ($charnum === 0 && strlen($string) >= $min_string_len) {
                    $strings[] = array('offset' => $i - strlen($string), 'string' => trim($string));
                }
                $string = '';
            }

            if (count($strings) > 0) {
                //TODO: Just return the first string for now.
                break;
            }
        }

        //TODO: Handle multiple strings by ranking them by length (longer), alphanumericness (more), and distance from top of stack (closer).
        //usort($strings, function($a, $b) {
        //    return strlen($b['string']) - strlen($a['string']);
        //});

        $output['strings'] = $strings;

        //TODO: Just replace entire output with the string we've found for now.
        if (count($strings) > 0) {
            $output = $strings[0];
        } else {
            $output = array('offset' => -1, 'string' => '');
        }

        return $app->json($output);
    }

    public function reprocess(Application $app, $id)
    {
        $user = $app['session']->get('user');

        if ($user && $user['admin']) {
            $app['db']->transactional(function($db) use ($id) {
                $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
                $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));
                $db->executeUpdate('DELETE FROM crashnotice WHERE crash = ?', array($id));

                $db->executeUpdate('UPDATE crash SET cmdline = NULL, thread = NULL, processed = FALSE WHERE id = ?', array($id));
            });
        }

        $return = $app['request']->get('return', null);
        if ($return) {
            return $app->redirect($return);
        }

        return $app->redirect($app['url_generator']->generate('dashboard'));
    }

    public function delete(Application $app, $id)
    {
        $user = $app['session']->get('user');

        if ($user && $user['admin']) {
            $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

            $app['db']->transactional(function($db) use ($id, $path) {
                \Filesystem::remove($path);

                $db->executeUpdate('DELETE FROM crash WHERE id = ?', array($id));
            });
        }

        $return = $app['request']->get('return', null);
        if ($return) {
            return $app->redirect($return);
        }

        return $app->redirect($app['url_generator']->generate('dashboard'));
    }

    public function dashboard(Application $app)
    {
        $user = $app['session']->get('user');

        if (!$user) {
            return $app->redirect($app['url_generator']->generate('login', array('return' => $app['request']->getPathInfo())));
        }

        $crashes = $app['db']->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, crash.owner, crash.cmdline, crash.processed, crash.failed, user.name, user.avatar, frame.module, frame.rendered, frame2.module as module2, frame2.rendered AS rendered2, (SELECT CONCAT(COUNT(*), \'-\', MIN(notice.severity)) FROM crashnotice JOIN notice ON crashnotice.notice = notice.id WHERE crashnotice.crash = crash.id) AS notice FROM crash LEFT JOIN user ON crash.owner = user.id LEFT JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 LEFT JOIN frame AS frame2 ON crash.id = frame2.crash AND crash.thread = frame2.thread AND frame2.frame = 1 WHERE owner = ? ORDER BY crash.timestamp DESC LIMIT 10', array($user['id']))->fetchAll();

        return $app['twig']->render('dashboard.html.twig', array(
            'crashes' => $crashes,
        ));
    }

    public function list_crashes(Application $app, $offset)
    {
        $user = $app['session']->get('user');

        if (!$user || !$user['id']) {
            return $app->redirect($app['url_generator']->generate('login', array('return' => $app['request']->getPathInfo())));
        }

        $userid = $user['admin'] ? $app['request']->get('user', null) : $user['id'];

        $where = '';
        $params = array();

        if ($offset || $userid) {
            $where .= 'WHERE ';

            if ($userid) {
                $where .= 'owner = ?';
                $params[] = $userid;

                if ($offset) {
                    $where .= ' AND ';
                }
            }

            if ($offset) {
                $where .= 'timestamp < FROM_UNIXTIME(?)';
                $params[] = $offset;
            }
        }

        $crashes = $app['db']->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, crash.owner, crash.cmdline, crash.processed, crash.failed, user.name, user.avatar, frame.module, frame.rendered, frame2.module as module2, frame2.rendered AS rendered2, (SELECT CONCAT(COUNT(*), \'-\', MIN(notice.severity)) FROM crashnotice JOIN notice ON crashnotice.notice = notice.id WHERE crashnotice.crash = crash.id) AS notice FROM crash LEFT JOIN user ON crash.owner = user.id LEFT JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 LEFT JOIN frame AS frame2 ON crash.id = frame2.crash AND crash.thread = frame2.thread AND frame2.frame = 1 ' . $where . ' ORDER BY crash.timestamp DESC LIMIT 20', $params)->fetchAll();

        return $app['twig']->render('list.html.twig', array(
            'userid' => ($user['admin'] ? $app['request']->get('user', null) : null),
            'offset' => $offset,
            'crashes' => $crashes,
        ));
    }
}

