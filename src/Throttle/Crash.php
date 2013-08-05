<?php

namespace Throttle;

use Silex\Application;

class Crash
{
    public function submit(Application $app)
    {
        //TODO
        //return $app->abort(503);

        $ip = $app['request']->getClientIp();

        $minidump = $app['request']->files->get('upload_file_minidump');

        if ($minidump === null || !$minidump->isValid() || $minidump->getClientSize() <= 0) {
            return $app->abort(400);
        }

        $id = \Filesystem::readRandomCharacters(12);

        $path = $app['root'] . '/dumps/' . substr($id, 0, 2);
        \Filesystem::createDirectory($path, 0755, true);
        $minidump->move($path, $id . '.dmp');

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

            //TODO As we don't need to query this until servers and permissions are added, just blindly inserting saves us a query.
            $app['db']->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($owner));
            $app['db']->executeUpdate('INSERT IGNORE INTO server (owner) VALUES (?)', array($owner));
            $server = '';
        }

        $metadata = json_encode($app['request']->request->all());

        $app['db']->executeUpdate('INSERT INTO crash (id, timestamp, ip, owner, metadata, server) VALUES (?, NOW(), INET_ATON(?), ?, ?, ?)', array($id, $ip, $owner, $metadata, $server));

        return $app['twig']->render('submit.txt.twig', array(
            'id' => $id,
        ));
    }

    public function details(Application $app, $id)
    {
        $crash = $app['db']->executeQuery('SELECT id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, owner, metadata, cmdline, processed, failed FROM crash WHERE id = ?', array($id))->fetch();

        if (empty($crash)) {
            if ($app['session']->getFlashBag()->get('internal')) {
                $app['session']->getFlashBag()->add('error_crash', 'Invalid Crash ID');

                return $app->redirect($app['url_generator']->generate('index'));
            } else {
                return $app->abort(404);
            }
        }

        $crash['metadata'] = json_decode($crash['metadata'], true);

        $stack = $app['db']->executeQuery('SELECT frame.frame, frame.module, frame.function, frame.file, frame.line, frame.offset FROM frame JOIN crash ON crash.id = frame.crash AND crash.thread = frame.thread WHERE crash = ? ORDER BY frame', array($id))->fetchAll();

        return $app['twig']->render('details.html.twig', array(
            'crash' => $crash,
            'stack' => $stack,
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

    public function reprocess(Application $app, $id)
    {
        $user = $app['session']->get('user');

        if ($user && $user['admin']) {
            $app['db']->transactional(function($db) use ($id) {
                $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
                $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));

                $db->executeUpdate('UPDATE crash SET cmdline = NULL, thread = NULL, processed = FALSE WHERE id = ?', array($id));
            });
        }

        $return = $app['request']->get('return', null);
        if ($return) {
            return $app->redirect($return);
        }

        return $app->redirect($app['url_generator']->generate('list'));
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

        return $app->redirect($app['url_generator']->generate('list'));
    }

    public function list_crashes(Application $app, $offset)
    {
        $user = $app['session']->get('user');

        if (!$user) {
            return $app->redirect($app['url_generator']->generate('login'));
        }

        $where = '';
        $params = array();

        if ($offset || !$user['admin']) {
            $where .= 'WHERE ';

            if (!$user['admin']) {
                $where .= 'owner = ?';
                $params[] = $user['id'];

                if ($offset) {
                    $where .= ' AND ';
                }
            }

            if ($offset) {
                $where .= 'timestamp < FROM_UNIXTIME(?)';
                $params[] = $offset;
            }
        }

        $crashes = $app['db']->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, crash.owner, crash.cmdline, crash.processed, crash.failed, user.name, user.avatar, frame.module, frame.function, frame.file, frame.line, frame.offset, frame2.module AS module2, frame2.function AS function2, frame2.file AS file2, frame2.line AS line2, frame2.offset AS offset2 FROM crash LEFT JOIN user ON crash.owner = user.id LEFT JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 LEFT JOIN frame AS frame2 ON crash.id = frame2.crash AND crash.thread = frame2.thread AND frame2.frame = 1 ' . $where . ' ORDER BY crash.timestamp DESC LIMIT 50', $params)->fetchAll();

        return $app['twig']->render('list.html.twig', array(
            'offset' => $offset,
            'crashes' => $crashes,
        ));
    }
}

