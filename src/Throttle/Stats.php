<?php

namespace Throttle;

use Silex\Application;

class Stats
{
    public function index(Application $app, $module = null, $function = null)
    {
        return $app['twig']->render('stats.html.twig', array(
            'module'   => $module,
            'function' => $function,
        ));
    }

    public function daily(Application $app, $module = null, $function = null)
    {
        $query = null;

        if ($function !== null) {
            $query = $app['db']->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY) AND frame = 0 AND module = ? AND function LIKE ? GROUP BY DATE(timestamp)', array($module, $function.'%'));
        } else if ($module !== null) {
            $query = $app['db']->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY) AND frame = 0 AND module LIKE ? GROUP BY DATE(timestamp)', array($module.'%'));
        } else {
            $query = $app['db']->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM crash WHERE timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(timestamp)');
        }

        $data = array();
        while ($row = $query->fetch()) {
            $data[$row['date']] = $row['count'];
        }

        $output = 'Date,Count'.PHP_EOL;
        for($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-'.$i.' days'));
            $count = array_key_exists($date, $data) ? $data[$date] : 0;
            $output .= $date.','.$count.PHP_EOL;
        }

        return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    public function top(Application $app, $module = null, $function = null)
    {
        $output = array();

        if ($function !== null) {
            $data = $app['db']->executeQuery('SELECT rendered, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE frame = 0 AND module = ? AND function LIKE ? GROUP BY rendered ORDER BY count DESC LIMIT 10', array($module, $function.'%')); 
            while ($row = $data->fetch()) {
                $output[] = array($app->escape($row['rendered']), $row['count'], false, false);
            }
        } else if ($module !== null) {
            $data = $app['db']->executeQuery('SELECT module, function,  COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE frame = 0 AND module LIKE ? GROUP BY function ORDER BY count DESC LIMIT 10', array($module.'%'));

            while ($row = $data->fetch()) {
                $output[] = array($app->escape($row['function'] ? $row['module'].'!'.$row['function'] : $row['module']), $row['count'], $row['module'], $row['function']);
            }
        } else {
            $data = $app['db']->executeQuery('SELECT module, function, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE frame = 0 GROUP BY module, function ORDER BY count DESC LIMIT 10');

            while ($row = $data->fetch()) {
                $output[] = array($app->escape($row['function'] ? $row['module'].'!'.$row['function'] : $row['module']), $row['count'], $row['module'], $row['function']);
            }
        }

        return $app->json($output, 200, array(
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    public function latest(Application $app, $module = null, $function = null)
    {
        $query = null;

        $limit = intval($app['request']->get('limit', 10));
        if ($limit <= 0) {
            $limit = 10;
        } else if ($limit > 200) {
            $limit = 200;
        }

        if ($function !== null) {
            $query = $app['db']->executeQuery('SELECT crash, rendered, cmdline, avatar FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread LEFT JOIN user ON crash.owner = user.id WHERE frame = 0 AND module = ? AND function LIKE ? ORDER BY timestamp DESC LIMIT ' . $limit, array($module, $function.'%'));
        } else if ($module !== null) {
            $query = $app['db']->executeQuery('SELECT crash, rendered, cmdline, avatar FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread LEFT JOIN user ON crash.owner = user.id WHERE frame = 0 AND module LIKE ? ORDER BY timestamp DESC LIMIT ' . $limit, array($module.'%'));
        } else {
            $query = $app['db']->executeQuery('SELECT crash, rendered, cmdline, avatar FROM crash JOIN frame ON crash = id AND frame.thread = crash.thread AND frame = 0 LEFT JOIN user ON crash.owner = user.id ORDER BY timestamp DESC LIMIT ' . $limit);
        }

        $output = array();
        while ($row = $query->fetch()) {
            $output[] = array($row['crash'], $app->escape($row['rendered']), (empty($row['cmdline']) ? '' : md5($row['cmdline'])), $row['avatar']);
        }

        return $app->json($output, 200, array(
            'Access-Control-Allow-Origin' => '*',
        ));
    }

}

