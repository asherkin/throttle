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

        $count = $app['db']->executeQuery('SELECT COUNT(*) AS count FROM crash WHERE owner = ? AND ip = INET_ATON(?) AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)', array($owner, $ip))->fetchColumn(0);

        if ($count > 6) {
            return $app['twig']->render('submit-reject.txt.twig');
        }

        $metadata = json_encode($app['request']->request->all());

        $app['db']->executeUpdate('INSERT INTO crash (id, timestamp, ip, owner, metadata, server) VALUES (?, NOW(), INET_ATON(?), ?, ?, ?)', array($id, $ip, $owner, $metadata, $server));

	// Move after it's in the DB, to avoid a race condition with the cleanup code.
        \Filesystem::createDirectory($path, 0755, true);
        $minidump->move($path, $id . '.dmp');

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
        $stack = $app['db']->executeQuery('SELECT frame, rendered FROM frame WHERE crash = ? AND thread = ? ORDER BY frame', array($id, $crash['thread']))->fetchAll();
        $modules = $app['db']->executeQuery('SELECT name, identifier, processed, present FROM module WHERE crash = ? ORDER BY name', array($id))->fetchAll();

        return $app['twig']->render('details.html.twig', array(
            'crash' => $crash,
            'notices' => $notices,
            'stack' => $stack,
            'modules' => $modules,
            'sys_error' => (isset($stack[0]['rendered']) ? (preg_match('/^engine(_srv)?\\.so!Sys_Error(_Internal)?\\(/', $stack[0]['rendered']) === 1) : false),
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

        if (!$user || !$user['admin']) {
            $app->abort(403);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.txt.gz';
        $logs = \Filesystem::pathExists($path) ? gzdecode(\Filesystem::readFile($path)) : null;

        return $app['twig']->render('logs.html.twig', array('logs' => $logs));
    }

    public function error(Application $app, $id)
    {
        $thread = $app['db']->executeQuery('SELECT thread FROM crash WHERE id = ? AND processed = 1 LIMIT 1', array($id))->fetchColumn(0);

        if ($thread === null) {
            $app->abort(404);
        }

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!\Filesystem::pathExists($path)) {
            $app->abort(404);
        }

        $minidump = \Filesystem::readFile($path);

        $output = array();

        $output['header'] = $header = unpack('A4magic/Lversion/Lstream_count/Lstream_offset', $minidump);

        $output['stream'] = $stream = unpack('Ltype/Lsize/Loffset', substr($minidump, $header['stream_offset'], 16));

        if ($stream['type'] !== 3) {
            throw new \RuntimeException('Bad stream type.');
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
            throw new \RuntimeException('Failed to find error string.' . PHP_EOL . print_r($output, true));
        }

        $output['string_start'] = $string_start = $thread['stack_offset'] + $error_offset;
        $string_length = 0;

        while (ord($minidump[$string_start + $string_length]) != 0 && $string_length < 256) {
            $string_length++;
        }

        $output['string_length'] = $string_length;

        $output['error_string'] = $error_string = substr($minidump, $string_start, $string_length);

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
            return $app->redirect($app['url_generator']->generate('login'));
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
            return $app->redirect($app['url_generator']->generate('login'));
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

        $crashes = $app['db']->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, crash.owner, crash.cmdline, crash.processed, crash.failed, user.name, user.avatar, frame.module, frame.rendered, frame2.module as module2, frame2.rendered AS rendered2, (SELECT CONCAT(COUNT(*), \'-\', MIN(notice.severity)) FROM crashnotice JOIN notice ON crashnotice.notice = notice.id WHERE crashnotice.crash = crash.id) AS notice FROM crash LEFT JOIN user ON crash.owner = user.id LEFT JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 LEFT JOIN frame AS frame2 ON crash.id = frame2.crash AND crash.thread = frame2.thread AND frame2.frame = 1 ' . $where . ' ORDER BY crash.timestamp DESC LIMIT 100', $params)->fetchAll();

        return $app['twig']->render('list.html.twig', array(
            'offset' => $offset,
            'crashes' => $crashes,
        ));
    }
}

