<?php

namespace App\Controller;

use Doctrine\DBAL\Driver\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class Crash extends AbstractController
{
    private $db;
    private $rootPath;

    public function __construct(Connection $db, $rootPath)
    {
        $this->db = $db;
        $this->rootPath = $rootPath;
    }

    /**
     * @Route("/submit", defaults={"_format": "txt"}, methods={"POST"})
     */
    public function submit(Request $request, LoggerInterface $logger, \Redis $redis)
    {
        //TODO
        //return $this->render('submit-disabled.txt.twig');

        $presubmit = $request->get('CrashSignature');
        if ($presubmit !== null) {
            $redis->hIncrBy('throttle:stats', 'crashes:presubmitted', 1);

            return new Response($this->presubmit($logger, $presubmit));
        }

        $redis->hIncrBy('throttle:stats', 'crashes:submitted', 1);

        $minidump = $request->files->get('upload_file_minidump');

        if ($minidump === null || !$minidump->isValid() || $minidump->getClientSize() <= 0) {
            $redis->hIncrBy('throttle:stats', 'crashes:rejected:no-minidump', 1);

            return $this->render('submit-empty.txt.twig');
        }

        $redis->hIncrBy('throttle:stats', 'crashes:submitted:bytes', $minidump->getClientSize());

        list($id, $path) = $this->generateId();

        $ip = $request->getClientIp();

        $owner = $request->request->get('UserID');
        if ($owner !== null) {
            $request->request->remove('UserID');

            if ($owner == 0) {
                $owner = null;
            } else if (stripos($owner, 'STEAM_') === 0) {
                $owner = explode(':', $owner);
                $owner = ($owner[2] << 1) | $owner[1];
                $owner = gmp_add('76561197960265728', $owner);
            } /* else if (gmp_cmp($owner, '0xFFFFFFFF') < 0) {
                $owner = gmp_add('76561197960265728', $owner);
            } */ else if (gmp_cmp(gmp_and($owner, '0xFFFFFFFF00000000'), '76561197960265728') !== 0) {
                $logger->warning('Bad owner provided in submit', array('id' => $id, 'owner' => $owner));
                $owner = null;
            }

            if ($owner !== null) {
                $this->db->executeUpdate('INSERT IGNORE INTO user (id) VALUES (?)', array($owner));
            }
        }

        $server = $request->request->get('ServerID');
        if ($server !== null) {
            $request->request->remove('ServerID');

            // TODO: Validate ID, then insert, just like above.
        }

        $count = 0;

        if ($owner !== null) {
            $count = $this->db->executeQuery('SELECT COUNT(*) AS count FROM crash JOIN crashnotice ON crash = id AND notice LIKE \'nosteam-%\' WHERE owner = ? AND ip = INET6_ATON(?) AND processed = 1 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH)', array($owner, $ip))->fetchColumn(0);
        } else {
            $count = $this->db->executeQuery('SELECT COUNT(*) AS count FROM crash JOIN crashnotice ON crash = id AND notice LIKE \'nosteam-%\' WHERE owner IS NULL AND ip = INET6_ATON(?) AND processed = 1 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH)', array($ip))->fetchColumn(0);
        }

        if ($count > 0) {
            $redis->hIncrBy('throttle:stats', 'crashes:rejected:no-steam', 1);

            return $this->render('submit-nosteam.txt.twig');
        }

        if ($owner !== null) {
            $count = $this->db->executeQuery('SELECT COUNT(*) AS count FROM crash WHERE owner = ? AND ip = INET6_ATON(?) AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)', array($owner, $ip))->fetchColumn(0);
        } else {
            $count = $this->db->executeQuery('SELECT COUNT(*) AS count FROM crash WHERE owner IS NULL AND ip = INET6_ATON(?) AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)', array($ip))->fetchColumn(0);
        }

        if ($count > 12) {
            $redis->hIncrBy('throttle:stats', 'crashes:rejected:rate-limit', 1);

            return $this->render('submit-reject.txt.twig');
        }

        $metadata = $request->request->all();

        $raw_metadata = null;
        $metadata_file = $request->files->get('upload_file_metadata');
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

        $this->db->executeUpdate('INSERT INTO crash (id, timestamp, ip, owner, metadata, cmdline) VALUES (?, NOW(), INET6_ATON(?), ?, ?, ?)', array($id, $ip, $owner, $metadata, $command_line));

        // Move after it's in the DB, to avoid a race condition with the cleanup code.
        \Filesystem::createDirectory($path, 0755, true);
        $minidump->move($path, $id . '.dmp');

        if ($raw_metadata) {
            $metapath = $path . '/' . $id . '.meta.txt.gz';
            \Filesystem::writeFile($metapath, gzencode($raw_metadata));
        }

        $redis->hIncrBy('throttle:stats', 'crashes:accepted', 1);

        // Special code for handling breakpad-uploaded minidumps.
        // FIXME: This is mainly a hack for testing Electron.
        if ($request->request->get('prod')) {
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

            return new Response($uuid);
        }

        return $this->render('submit.txt.twig', array(
            'id' => $id,
        ));
    }

    /**
     * @Route("/dashboard", name="dashboard")
     */
    public function dashboard(Request $request)
    {
        $offset = $request->get('offset');
        $userid = $request->get('user');

        $this->denyAccessUnlessGranted('ROLE_USER');

        $currentUser = $this->getUser();

        $shared = $this->db->executeQuery('SELECT share.owner AS id, user.name, user.avatar FROM share LEFT JOIN user ON share.owner = user.id WHERE share.user = ? AND accepted IS NOT NULL ORDER BY accepted ASC', [$currentUser->getId()])->fetchAll();

        array_unshift($shared, [
            'id' => $currentUser->getId(),
            'name' => $currentUser->getName(),
            'avatar' => $currentUser->getAvatar(),
        ]);

        $allowed = null;
        if (!$currentUser->isAdmin()) {
            foreach ($shared as $user) {
                $allowed[] = $user['id'];
            }

            if ($userid !== null && !in_array($userid, $allowed, true)) {
                throw $this->createAccessDeniedException();
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

        $crashes = $this->db->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) as timestamp, crash.owner, crash.cmdline, crash.processed, crash.failed, user.name, user.avatar, frame.module, frame.rendered, frame2.module as module2, frame2.rendered AS rendered2, (SELECT CONCAT(COUNT(*), \'-\', MIN(notice.severity)) FROM crashnotice JOIN notice ON crashnotice.notice = notice.id WHERE crashnotice.crash = crash.id) AS notice FROM crash LEFT JOIN user ON crash.owner = user.id LEFT JOIN frame ON crash.id = frame.crash AND crash.thread = frame.thread AND frame.frame = 0 LEFT JOIN frame AS frame2 ON crash.id = frame2.crash AND crash.thread = frame2.thread AND frame2.frame = 1 ' . $where . ' ORDER BY crash.timestamp DESC LIMIT 20', $params, $types)->fetchAll();

        return $this->render('dashboard.html.twig', array(
            'userid' => $userid,
            'shared' => $shared,
            'offset' => $offset,
            'crashes' => $crashes,
        ));
    }

    /**
     * @Route("/{uuid<[0-9a-fA-F-]{36}>}", name="details_uuid")
     */
    public function detailsUuid($uuid)
    {
        $uuid = substr($uuid, 20, 3) . substr($uuid, 24);
        $uuid = str_split($uuid);

        $bid = '';
        for ($i = 0; $i < 15; $i++) {
            $bid .= sprintf('%04b', hexdec($uuid[$i]));
        }
        $bid = str_split($bid, 5);

        $id = '';
        $map = array_merge(range('a', 'z'), range('2', '7'));
        for ($i = 0; $i < 12; $i++) {
            $id .= $map[bindec($bid[$i])];
        }

        return $this->redirectToRoute('details', [
            'id' => $id,
        ]);
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}", name="details")
     */
    public function details(Request $request, $appConfig, $id)
    {
        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            if ($request->getSession()->getFlashBag()->get('internal')) {
                $this->addFlash('error_crash', 'That Crash ID does not exist.');

                return $this->redirectToRoute('index');
            }
    
            throw $this->createNotFoundException();
        }

        $crash = $this->db->executeQuery('SELECT crash.id, UNIX_TIMESTAMP(crash.timestamp) AS timestamp, INET6_NTOA(ip) AS ip, owner, metadata, cmdline, thread, processed, failed, stackhash, UNIX_TIMESTAMP(crash.lastview) AS lastview, user.name FROM crash LEFT JOIN user ON user.id = crash.owner WHERE crash.id = ?', array($id))->fetch();

        if ($crash['lastview'] === null || (time() - $crash['lastview']) > (60 * 60 * 24)) {
            $this->db->executeUpdate('UPDATE crash SET lastview = NOW() WHERE id = ?', array($id));
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

        $notices = $this->db->executeQuery('SELECT severity, text FROM crashnotice JOIN notice ON notice.id = crashnotice.notice WHERE crash = ?', array($id))->fetchAll();
        $stack = $this->db->executeQuery('SELECT frame, rendered, url FROM frame WHERE crash = ? AND thread = ? ORDER BY frame', array($id, $crash['thread']))->fetchAll();
        $modules = $this->db->executeQuery('SELECT name, identifier, processed, present, HEX(base) AS base FROM module WHERE crash = ? ORDER BY name', array($id))->fetchAll();
        $stats = $this->db->executeQuery('SELECT COUNT(DISTINCT crash.owner) AS owners, COUNT(DISTINCT crash.ip) AS ips, COUNT(*) AS crashes FROM crash, (SELECT owner, stackhash FROM crash WHERE id = ?) AS this WHERE this.stackhash = crash.stackhash', array($id))->fetch();

        return $this->render('details.html.twig', array(
            'crash' => $crash,
            'can_manage' => $can_manage,
            'notices' => $notices,
            'stack' => $stack,
            'modules' => $modules,
            'stats' => $stats,
            'outdated' => isset($appConfig['accelerator'])  && isset($crash['metadata']['ExtensionVersion']) && version_compare($crash['metadata']['ExtensionVersion'], $appConfig['accelerator'], '<'),
            'has_error_string' => (isset($stack[0]['rendered']) ? (preg_match('/^engine(_srv)?\\.so!Sys_Error(_Internal)?\\(/', $stack[0]['rendered']) === 1) : false),
        ));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/download", name="download")
     */
    public function download($id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        $path = $this->rootPath . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!\Filesystem::pathExists($path)) {
            throw $this->createNotFoundException();
        }

        return $this->file($path, 'crash_'.$id.'.dmp');
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/view", name="view")
     */
    public function view($id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('view.html.twig', array('id' => $id));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/logs", name="logs")
     */
    public function logs($id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        $path = $this->rootPath . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.txt';

        $logs = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $logs = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $logs = \Filesystem::readFile($path);
        }

        return $this->render('logs.html.twig', array('id' => $id, 'logs' => $logs));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/metadata", name="metadata")
     */
    public function metadata($id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        $path = $this->rootPath . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.meta.txt';

        $logs = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $logs = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $logs = \Filesystem::readFile($path);
        }

        return $this->render('logs.html.twig', array('id' => $id, 'logs' => $logs));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/console", name="console")
     */
    public function console($id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        $path = $this->rootPath . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.meta.txt';

        $metadata = null;
        if (\Filesystem::pathExists($path . '.gz')) {
            $metadata = gzdecode(\Filesystem::readFile($path . '.gz'));
        } else if (\Filesystem::pathExists($path)) {
            $metadata = \Filesystem::readFile($path);
        }

        // Extract the console output from the full metadata.
        $ret = preg_match('/(?<=-------- CONSOLE HISTORY BEGIN --------)[^\\x00]+(?=-------- CONSOLE HISTORY END --------)/i', $metadata, $console);

        if ($ret !== 1) {
            throw $this->createNotFoundException();
        }

        $console = $console[0]; // Get the console output.
        $console = trim($console); // Remove the extra newlines from the markers.
        $console = str_replace("\r\n", PHP_EOL, $console); // Normalize line endings.

        // Split the console output into individual prints.
        preg_match_all('/(\\d+)\\((\\d+\\.?\\d*)\\):  ([^\\x00]*?)(?=(?:\\d+\\(\\d+\\.\\d+\\):  )|$)/', $console, $console, PREG_SET_ORDER);

        $console = array_reverse($console); // Flip them back into chronological order.

        return $this->render('console.html.twig', array('id' => $id, 'console' => $console));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/error", defaults={"_format": "json"}, name="error")
     */
    public function error($id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        $path = $this->rootPath . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!\Filesystem::pathExists($path)) {
            throw $this->createNotFoundException();
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

        $thread = $this->db->executeQuery('SELECT thread FROM crash WHERE id = ? AND processed = 1 LIMIT 1', array($id))->fetchColumn(0);
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
            return $this->json(array('string' => 'Failed to extract error message.'));
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

        return $this->json(array('string' => $error_string));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/carburetor", name="carburetor")
     */
    public function carburetor(Request $request, $id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('carburetor.html.twig', array(
            'id' => $id,
            'symbols' => $request->get('symbols'),
        ));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/carburetor/data", defaults={"_format": "json"}, name="carburetor_data")
     */
    public function carburetor_data(Request $request, $id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        $config = $this->rootPath.'/app/carburetor-config.json';
        if ($request->get('symbols') === 'no') {
            $config = $this->rootPath.'/app/carburetor-config-no-symbols.json';
        }

        $path = $this->rootPath . '/dumps/' . substr($id, 0, 2) . '/' . $id . '.dmp';

        if (!\Filesystem::pathExists($path)) {
            throw $this->createNotFoundException();
        }

        set_time_limit(120);

        list($stdout, $stderr) = execx($this->rootPath.'/bin/carburetor %s %s', $config, $path);

        return new Response($stdout, 200, array(
            'Content-Type' => 'application/json',
        ));
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/reprocess", methods={"POST"}, name="reprocess")
     */
    public function reprocess(Request $request, $id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        if (!$this->getUser()->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        $this->db->transactional(function($db) use ($id) {
            $db->executeUpdate('DELETE FROM frame WHERE crash = ?', array($id));
            $db->executeUpdate('DELETE FROM module WHERE crash = ?', array($id));
            $db->executeUpdate('DELETE FROM crashnotice WHERE crash = ?', array($id));

            $db->executeUpdate('UPDATE crash SET thread = NULL, processed = FALSE, failed = FALSE, stackhash = NULL WHERE id = ?', array($id));
        });

        $return = $request->get('return');
        if ($return) {
            return $this->redirect($return);
        }

        return $this->redirectToRoute('dashboard');
    }

    /**
     * @Route("/{id<[0-9a-zA-Z]{12}>}/delete", methods={"POST"}, name="delete")
     */
    public function delete(Request $request, $id)
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $can_manage = $this->canUserManage($id);
        if ($can_manage === null) {
            throw $this->createNotFoundException();
        }

        if (!$can_manage) {
            throw $this->createAccessDeniedException();
        }

        $this->db->executeUpdate('DELETE FROM crash WHERE id = ?', array($id));

        $return = $request->get('return');
        if ($return) {
            return $this->redirect($return);
        }

        return $this->redirectToRoute('dashboard');
    }

    private function generateId()
    {
        for ($i = 0; $i < 10; $i++) {
            $id = \Filesystem::readRandomCharacters(12);
            $path = $this->rootPath . '/dumps/' . substr($id, 0, 2);

            if (\Filesystem::pathExists($path . '/' . $id . '.dmp')) {
                continue;
            }

            return array($id, $path);
        }

        throw new \Exception('MINIDUMP COLLISION');
    }

    private function canUserManage($crash)
    {
        $user = $this->getUser();

        if (!$user || $user->isAdmin()) {
            // Hacky, but execute a query to check if the crash doesn't exist at all.
            $query = $this->db->executeQuery('SELECT 1 FROM crash WHERE crash.id = ?', [$crash])->fetchColumn(0);

            return ($query === false) ? null : ($user && $user->isAdmin());
        }

        $query = $this->db->executeQuery('SELECT COALESCE(crash.owner = ? OR EXISTS (SELECT TRUE FROM share WHERE share.owner = crash.owner AND share.user = ?), 0) AS manage FROM crash WHERE crash.id = ?', [ $user->getId(), $user->getId(), $crash ])->fetchColumn(0);

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

    private function presubmit(LoggerInterface $logger, string $signature)
    {
        //$logger->info('Presubmit: '.$signature);

        try {
            $signature = self::parsePresubmitSignature($signature);
        } catch (\Exception $e) {
            $logger->warning('Error parsing presubmit: '.$signature, [ 'exception' => $e ]);

            return 'E|'.$e->getMessage();
        }

        // TODO: Determine whether we want the crash dump...
        $return = 'Y|';
        foreach ($signature->modules as $module) {
            // This query in a loop is *a lot* faster than other methods.
            $exists = $this->db->executeQuery('SELECT TRUE FROM module WHERE name = ? AND identifier = ? AND present = 1 LIMIT 1', [$module->file, $module->identifier])->fetchColumn(0);
            $return .= ($exists === false) ? 'Y' : 'N';
        }

        // Stick a random presubmit token on the end for testing.
        $return .= '|'.md5($return);

        return $return;
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
}

