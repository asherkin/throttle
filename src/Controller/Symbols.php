<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Symbols extends AbstractController
{
    /**
     * @Route("/symbols/submit", defaults={"_format": "txt"}, methods={"POST"})
     */
    public function submit(Request $request, LoggerInterface $logger, \Redis $redis, $rootPath)
    {
        $data = $request->get('symbol_file');
        if ($data === null) {
            $data = $request->getContent();
        }

        $redis->hIncrBy('throttle:stats', 'symbols:submitted', 1);
        $redis->hIncrBy('throttle:stats', 'symbols:submitted:bytes', strlen($data));

        $lines = preg_split('/\r?\n/', trim($data));

        if ($lines === false || !preg_match('/^MODULE (?P<operatingsystem>[^ ]++) (?P<architecture>[^ ]++) (?P<id>[a-fA-F0-9]++) (?P<name>[^\\/\\\\\r\n]++)$/m', $lines[0], $info)) {
            $logger->warning('Invalid symbol file', ['header' => ($lines ? $lines[0] : null)]);
            $redis->hIncrBy('throttle:stats', 'symbols:rejected:invalid', 1);

            return new \Symfony\Component\HttpFoundation\Response('Invalid symbol file', 400);
        }

        if ($info['operatingsystem'] === 'Linux') {
            $functions = 0;

            foreach ($lines as $line) {
                list($type) = explode(' ', $line, 2);

                if ($type === 'STACK') {
                    break;
                }

                if ($type === 'FUNC') {
                    ++$functions;
                }
            }

            if ($functions === 0) {
                $redis->hIncrBy('throttle:stats', 'symbols:rejected:no-functions', 1);

                return new \Symfony\Component\HttpFoundation\Response('Symbol file had no FUNC records, please update to Accelerator 2.4.3 or later', 400);
            }
        }

        $path = $rootPath.'/symbols/public/'.$info['name'].'/'.$info['id'];

        \Filesystem::createDirectory($path, 0755, true);

        $file = $info['name'];
        if (pathinfo($file, PATHINFO_EXTENSION) == 'pdb') {
            $file = substr($file, 0, -4);
        }

        \Filesystem::writeFile($path.'/'.$file.'.sym.gz', gzencode($data));

        $redis->hIncrBy('throttle:stats', 'symbols:accepted', 1);

        return $this->render('submit-symbols.txt.twig', [
            'module' => $info,
        ]);
    }
}
