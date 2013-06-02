<?php

namespace Throttle;

use Silex\Application;

class Crash
{
    public function submit(Application $app)
    {
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
        if ($owner !== null) {
            $app['request']->request->remove('UserID');
        }

        $metadata = json_encode($app['request']->request->all());

        $app['db']->executeUpdate('INSERT INTO crash VALUES(?, NOW(), INET_ATON(?), ?, ?, DEFAULT, DEFAULT, DEFAULT)', array($id, $ip, $owner, $metadata));

        return $app['twig']->render('submit.txt.twig', array(
            'id' => $id,
        ));
    }

    public function details(Application $app, $id)
    {
        $crash = $app['db']->executeQuery('SELECT id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, metadata, cmdline, processed FROM crash WHERE id = ?', array($id))->fetch();

        if (empty($crash)) {
            if ($app['session']->getFlashBag()->get('internal')) {
                $app['session']->getFlashBag()->add('error', 'Invalid Crash ID');
                return $app->redirect($app['url_generator']->generate('index'));
            } else {
                return $app->abort(404);
            }
        }

        $crash['metadata'] = json_decode($crash['metadata'], true);

        return $app['twig']->render('details.html.twig', array(
            'crash' => $crash,
        ));
    }

    public function stack(Application $app, $id)
    {
        $stack = $app['db']->executeQuery('SELECT frame.frame, frame.module, frame.function, frame.file, frame.line, frame.offset FROM frame JOIN crash ON crash.id = frame.crash AND crash.thread = frame.thread WHERE crash = ? ORDER BY frame', array($id))->fetchAll();

        return $app['twig']->render('stack.html.twig', array(
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
            $app['db']->transactional(function($db) use($id) {
                $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
                $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));

                $db->executeUpdate('UPDATE crash SET cmdline = NULL, thread = NULL, processed = FALSE WHERE id = ?', array($id));
            });
        }

        return $app->redirect($app['url_generator']->generate('list'));
    }

    public function delete(Application $app, $id) 
    {
        $user = $app['session']->get('user');

        if ($user && $user['admin']) {
            $path = $app['root'] . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

            $app['db']->transactional(function($db) use($id, $path) {
                \Filesystem::remove($path);

                $db->executeUpdate('DELETE FROM crash WHERE id = ?', array($id));
            });
        }

        return $app->redirect($app['url_generator']->generate('list'));
    }

    public function list_crashes(Application $app)
    {
        $user = $app['session']->get('user');

        if (!$user) {
            return $app->redirect($app['url_generator']->generate('login'));
        }

        $check_user = $user['admin'] ? '' : 'WHERE owner = ?';
        $crashes = $app['db']->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, crash.owner, crash.cmdline, crash.processed, frame.module, frame.function, frame.file, frame.line, frame.offset, frame2.module AS module2, frame2.function AS function2, frame2.file AS file2, frame2.line AS line2, frame2.offset AS offset2 FROM crash LEFT JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 LEFT JOIN frame AS frame2 ON crash.id = frame2.crash AND crash.thread = frame2.thread AND frame2.frame = 1 ' . $check_user . ' ORDER BY crash.timestamp DESC LIMIT 100', array($user['id']))->fetchAll();

        return $app['twig']->render('list.html.twig', array(
            'crashes' => $crashes,
        ));
    }
}

