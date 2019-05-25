<?php

namespace App\Controller;

use Silex\Application;

class Symbols
{
    public function submit()
    {
        $data = $app['request']->get('symbol_file');
        if ($data === null) {
            $data = $app['request']->getContent();
        }

        $app['redis']->hIncrBy('throttle:stats', 'symbols:submitted', 1);
        $app['redis']->hIncrBy('throttle:stats', 'symbols:submitted:bytes', strlen($data));

        $lines = phutil_split_lines($data, false);

        if (!preg_match('/^MODULE (?P<operatingsystem>[^ ]++) (?P<architecture>[^ ]++) (?P<id>[a-fA-F0-9]++) (?P<name>[^\\/\\\\\r\n]++)$/m', $lines[0], $info)) {
            $app['monolog']->warning('Invalid symbol file: ' . $lines[0]);
            $app['redis']->hIncrBy('throttle:stats', 'symbols:rejected:invalid', 1);

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
                    $functions++;
                }
            }

            if ($functions === 0) {
                $app['redis']->hIncrBy('throttle:stats', 'symbols:rejected:no-functions', 1);
                return new \Symfony\Component\HttpFoundation\Response('Symbol file had no FUNC records, please update to Accelerator 2.4.3 or later', 400);
            }
        }

        $path = $app['root'] . '/symbols/public/' . $info['name'] . '/' . $info['id'];

        \Filesystem::createDirectory($path, 0755, true);

        $file = $info['name'];
        if (pathinfo($file, PATHINFO_EXTENSION) == 'pdb') {
            $file = substr($file, 0, -4);
        }

        \Filesystem::writeFile($path . '/' . $file . '.sym.gz', gzencode($data));

        $app['redis']->hIncrBy('throttle:stats', 'symbols:accepted', 1);

        return $this->render('submit-symbols.txt.twig', array(
            'module' => $info,
        ));
    }
}

