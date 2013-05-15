<?php

namespace Accelerator;

use Silex\Application;

class Symbols
{
    public function submit(Application $app)
    {
        $data = $app['request']->getContent();
        $module = head(phutil_split_lines($data));

        if (!preg_match('/^MODULE (?P<operatingsystem>[^ ]++) (?P<architecture>[^ ]++) (?P<id>[a-fA-F0-9]++) (?P<name>[^\\/\\\\\r\n]++)$/m', $module, $info)) {
            return new \Symfony\Component\HttpFoundation\Response('Invalid symbol file', 400);
        }

        $path = $app['root'] . '/symbols/public/' . $info['name'] . '/' . $info['id'];

        \Filesystem::createDirectory($path, 0755, true);

        $file = $info['name'];
        if (pathinfo($file, PATHINFO_EXTENSION) == 'pdb') {
            $file = substr($file, 0, -4);
        }

        \Filesystem::writeFile($path . '/' . $file . '.sym', $data);

        return $app['twig']->render('submit-symbols.txt.twig', array(
            'module' => $info,
        ));
    }
}

