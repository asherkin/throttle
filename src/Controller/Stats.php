<?php

namespace App\Controller;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class Stats extends AbstractController
{
    const METRIC_GRAPH = 'submitted.submitted';

    /**
     * @Route("/stats/today", name="stats_today")
     */
    public function today()
    {
        $key = 'throttle.rrd.today';
        $data = \apcu_fetch($key);
        if ($data === false) {
            $historical = self::getRawRrdData('-24hours', 300, self::METRIC_GRAPH);
            $historical = array_reduce($historical, function ($r, $d) {
                return $r + $d[1];
            }, 0);

            $data = round($historical);
            \apcu_add($key, $data, 300);
        }

        return new \Symfony\Component\HttpFoundation\Response($data, 200, array(
            'Content-Type' => 'text/plain',
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    /**
     * @Route("/stats/lifetime", name="stats_lifetime")
     */
    public function lifetime(\Redis $redis)
    {
        $key = 'throttle.rrd.lifetime';
        $data = \apcu_fetch($key);
        if ($data === false) {
            $data = $redis->hGet('throttle:stats', 'crashes:submitted');

            \apcu_add($key, $data, 10);
        }

        return new \Symfony\Component\HttpFoundation\Response($data, 200, array(
            'Content-Type' => 'text/plain',
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    /**
     * @Route("/stats/unique", name="stats_unique")
     */
    public function unique(Connection $db)
    {
        $key = 'throttle.rrd.unique';
        $data = \apcu_fetch($key);
        if ($data === false) {
            $query = $db->executeQuery('SELECT COUNT(*) AS count FROM (SELECT DISTINCT cmdline FROM crash WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS _');
            $data = $query->fetchColumn(0);

            \apcu_add($key, $data, 10);
        }

        return new \Symfony\Component\HttpFoundation\Response($data, 200, array(
            'Content-Type' => 'text/plain',
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    /**
     * @Route("/stats/daily/{module}/{function}", name="stats_daily")
     */
    public function daily(Connection $db, $module = null, $function = null)
    {
        if ($module === null && $function === null) {
            $output = self::getCsvRrdData('-90days', 86400, self::METRIC_GRAPH);
            return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
                'Content-Type' => 'text/csv',
                'Access-Control-Allow-Origin' => '*',
            ));
        }

        $query = null;

        if ($function !== null) {
            if (preg_match('/^0x[0-9a-f]+$/', $function)) {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 90 DAY) AND frame = 0 AND module LIKE ? AND function = \'\' AND offset = ? GROUP BY DATE(timestamp)', array($module, $function));
            } else {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 90 DAY) AND frame = 0 AND module LIKE ? AND function LIKE ? GROUP BY DATE(timestamp)', array($module, $function));
            }
        } else if ($module !== null) {
            if (preg_match('/^%?(?:[0-9a-f]{8})+%?$/', $module)) {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM crash WHERE timestamp > DATE_SUB(NOW(), INTERVAL 90 DAY) AND stackhash LIKE ? GROUP BY DATE(timestamp)', array($module));
            } else {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 90 DAY) AND frame = 0 AND module LIKE ? GROUP BY DATE(timestamp)', array($module));
            }
        } else {
            $query = $db->executeQuery('SELECT DATE(timestamp) AS date, COUNT(*) AS count FROM crash WHERE timestamp > DATE_SUB(NOW(), INTERVAL 90 DAY) GROUP BY DATE(timestamp)');
        }

        $data = array();
        while ($row = $query->fetch()) {
            $data[$row['date']] = $row['count'];
        }

        $output = 'Date,Crash Reports'.PHP_EOL;
        for($i = 89; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-'.$i.' days'));
            $count = array_key_exists($date, $data) ? $data[$date] : 0;
            $output .= $date.','.$count.PHP_EOL;
        }

        return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
            'Content-Type' => 'text/csv',
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    /**
     * @Route("/stats/hourly/{module}/{function}", name="stats_hourly")
     */
    public function hourly(Connection $db, $module = null, $function = null)
    {
        if ($module === null && $function === null) {
            $output = self::getCsvRrdData('-7days', 3600, self::METRIC_GRAPH);
            return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
                'Content-Type' => 'text/csv',
                'Access-Control-Allow-Origin' => '*',
            ));
        }

        $query = null;

        if ($function !== null) {
            if (preg_match('/^0x[0-9a-f]+$/', $function)) {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, HOUR(timestamp) AS hour, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 168 HOUR) AND frame = 0 AND module LIKE ? AND function = \'\' AND offset = ? GROUP BY DATE(timestamp), HOUR(timestamp)', array($module, $function));
            } else {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, HOUR(timestamp) AS hour, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 168 HOUR) AND frame = 0 AND module LIKE ? AND function LIKE ? GROUP BY DATE(timestamp), HOUR(timestamp)', array($module, $function));
            }
        } else if ($module !== null) {
            if (preg_match('/^%?(?:[0-9a-f]{8})+%?$/', $module)) {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, HOUR(timestamp) AS hour, COUNT(*) AS count FROM crash WHERE timestamp > DATE_SUB(NOW(), INTERVAL 168 HOUR) AND stackhash LIKE ? GROUP BY DATE(timestamp), HOUR(timestamp)', array($module));
            } else {
                $query = $db->executeQuery('SELECT DATE(timestamp) AS date, HOUR(timestamp) AS hour, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE timestamp > DATE_SUB(NOW(), INTERVAL 168 HOUR) AND frame = 0 AND module LIKE ? GROUP BY DATE(timestamp), HOUR(timestamp)', array($module));
            }
        } else {
            $query = $db->executeQuery('SELECT DATE(timestamp) AS date, HOUR(timestamp) AS hour, COUNT(*) AS count FROM crash WHERE timestamp > DATE_SUB(NOW(), INTERVAL 168 HOUR) GROUP BY DATE(timestamp), HOUR(timestamp)');
        }

        $data = array();
        while ($row = $query->fetch()) {
            $data[$row['date'].'-'.$row['hour']] = $row['count'];
        }

        $output = 'Date,Crash Reports'.PHP_EOL;
        for($i = 167; $i >= 0; $i--) {
            $date = date('Y-m-d-G', strtotime('-'.$i.' hours'));
            $count = array_key_exists($date, $data) ? $data[$date] : 0;
            $output .= $date.','.$count.PHP_EOL;
        }

        return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
            'Content-Type' => 'text/csv',
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    /**
     * @Route("/stats/top/{module}/{function}", name="stats_top")
     */
    public function top(Request $request, Connection $db, $module = null, $function = null)
    {
        $output = array();

        $scope = '';
        $requestScope = $request->get('scope', 'all');
        switch ($requestScope) {
            case 'day':
                $scope = 'AND timestamp > DATE_SUB(NOW(), INTERVAL 1 DAY)';
                break;
            case 'week':
                $scope = 'AND timestamp > DATE_SUB(NOW(), INTERVAL 1 WEEK)';
                break;
            case 'month':
                $scope = 'AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MONTH)';
                break;
        }

        if ($function !== null) {
            if (preg_match('/^0x[0-9a-f]+$/', $function)) {
                $data = $db->executeQuery('SELECT rendered, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE frame = 0 AND module LIKE ? AND function = \'\' AND offset = ? '.$scope.' GROUP BY rendered ORDER BY count DESC LIMIT 10', array($module, $function));
            } else {
                $data = $db->executeQuery('SELECT rendered, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE frame = 0 AND module LIKE ? AND function LIKE ? '.$scope.' GROUP BY rendered ORDER BY count DESC LIMIT 10', array($module, $function));
            }

            while ($row = $data->fetch()) {
                $output[] = array(htmlspecialchars($row['rendered'], ENT_QUOTES, 'UTF-8'), $row['count'], false, false);
            }
        } else if ($module !== null) {
            if (preg_match('/^%?(?:[0-9a-f]{8})+%?$/', $module)) {
                $data = $db->executeQuery('SELECT rendered, COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread AND stackhash LIKE ? WHERE frame = 0 '.$scope.' GROUP BY rendered ORDER BY count DESC LIMIT 10', array($module));

                while ($row = $data->fetch()) {
                    $output[] = array(htmlspecialchars($row['rendered'], ENT_QUOTES, 'UTF-8'), $row['count'], false, false);
                }
            } else {
                $data = $db->executeQuery('SELECT module, COALESCE(NULLIF(function, \'\'), offset) AS function,  COUNT(*) AS count FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread WHERE frame = 0 AND module LIKE ? '.$scope.' GROUP BY COALESCE(NULLIF(function, \'\'), offset) ORDER BY count DESC LIMIT 10', array($module));

                while ($row = $data->fetch()) {
                    $output[] = array(htmlspecialchars(($row['function'] ? $row['module'].'!'.$row['function'] : $row['module']), ENT_QUOTES, 'UTF-8'), $row['count'], $row['module'], $row['function']);
                }
            }
        } else {
            $data = $db->executeQuery('SELECT crashmodule AS module, crashfunction AS function, COUNT(*) AS count FROM crash WHERE 1=1 '.$scope.' GROUP BY module, function ORDER BY count DESC LIMIT 10');

            while ($row = $data->fetch()) {
                $output[] = array(htmlspecialchars(($row['function'] ? $row['module'].'!'.$row['function'] : $row['module']), ENT_QUOTES, 'UTF-8'), $row['count'], $row['module'], $row['function']);
            }
        }

        return $this->json($output, 200, array(
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    /**
     * @Route("/stats/latest/{module}/{function}", name="stats_latest")
     */
    public function latest(Request $request, Connection $db, $module = null, $function = null)
    {
        $query = null;

        $limit = intval($request->get('limit', 10));
        if ($limit <= 0) {
            $limit = 10;
        } else if ($limit > 200) {
            $limit = 200;
        }

        if ($function !== null) {
            if (preg_match('/^0x[0-9a-f]+$/', $function)) {
                $query = $db->executeQuery('SELECT crash, rendered, cmdline, avatar FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread LEFT JOIN user ON crash.owner = user.id WHERE frame = 0 AND module LIKE ? AND function = \'\' AND offset = ? ORDER BY timestamp DESC LIMIT ' . $limit, array($module, $function));
            } else {
                $query = $db->executeQuery('SELECT crash, rendered, cmdline, avatar FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread LEFT JOIN user ON crash.owner = user.id WHERE frame = 0 AND module LIKE ? AND function LIKE ? ORDER BY timestamp DESC LIMIT ' . $limit, array($module, $function));
            }
        } else if ($module !== null) {
            if (preg_match('/^%?(?:[0-9a-f]{8})+%?$/', $module)) {
                $query = $db->executeQuery('SELECT crash, rendered, cmdline, avatar FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread AND crash.stackhash LIKE ? LEFT JOIN user ON crash.owner = user.id WHERE frame = 0 ORDER BY timestamp DESC LIMIT ' . $limit, array($module));
            } else {
                $query = $db->executeQuery('SELECT crash, rendered, cmdline, avatar FROM frame JOIN crash ON id = crash AND crash.thread = frame.thread LEFT JOIN user ON crash.owner = user.id WHERE frame = 0 AND module LIKE ? ORDER BY timestamp DESC LIMIT ' . $limit, array($module));
            }
        } else {
            $query = $db->executeQuery('SELECT crash, rendered, cmdline, avatar FROM crash JOIN frame ON crash = id AND frame.thread = crash.thread AND frame = 0 LEFT JOIN user ON crash.owner = user.id ORDER BY timestamp DESC LIMIT ' . $limit);
        }

        $output = array();
        while ($row = $query->fetch()) {
            $output[] = array($row['crash'], htmlspecialchars($row['rendered'], ENT_QUOTES, 'UTF-8'), (empty($row['cmdline']) ? '' : md5($row['cmdline'])), $row['avatar']);
        }

        return $this->json($output, 200, array(
            'Access-Control-Allow-Origin' => '*',
        ));
    }

    /**
     * @Route("/stats/{module}/{function}", name="stats")
     */
    public function index($module = null, $function = null)
    {
        return $this->render('stats.html.twig', array(
            'module'   => $module,
            'function' => $function,
        ));
    }

    private static function getRawRrdData($start, $step, $metric)
    {
        $key = 'throttle.rrd.'.$metric.'.'.$start.'.'.$step;
        $data = \apcu_fetch($key);
        if ($data !== false) {
            return $data;
        }

        $metric = str_replace('.', '-', $metric);
        list($data,) = \execx('/usr/bin/rrdtool graph - --start %s --step %d --imgformat CSV %s %s', $start, $step, 'DEF:value=/var/lib/munin/fennec/fennec-throttle_'.$metric.'-d.rrd:42:AVERAGE', 'LINE1:value#000000:value');
        $data = \phutil_split_lines($data);
        array_shift($data);
        $data = array_map(function($d) use ($step) {
            $d = str_getcsv($d);
            $d[0] = ((int)$d[0]) - $step;
            $d[1] = round(((float)$d[1]) * $step);
            return $d;
        }, $data);

        \apcu_add($key, $data, 300);
        return $data;
    }

    private static function getRrdData($start, $step, $metric)
    {
        $data = self::getRawRrdData($start, $step, $metric);
        if (!empty($data)) {
            $last_stamp = end($data)[0] + $step;

            // TODO: Iteratively step down the periods, required for more than one day.
            $last_period = self::getRawRrdData($last_stamp, 300, $metric);
            $last_period = array_reduce($last_period, function($r, $d) {
                return $r + $d[1];
            }, 0);

            $data[] = [
                $last_stamp,
                round($last_period),
            ];
        }
        return $data;
    }

    private static function getCsvRrdData($start, $step, $metric)
    {
        $key = 'throttle.rrd.'.$metric.'.'.$start.'.'.$step.'.csv';
        $data = \apcu_fetch($key);
        if ($data !== false) {
            return $data;
        }

        $date_format = 'Y-m-d-H-i-s';
        if ($step >= 86400) {
            $date_format = 'Y-m-d';
        } else if ($step >= 3600) {
            $date_format = 'Y-m-d-H';
        } else if ($step >= 60) {
            $date_format = 'Y-m-d-H-i';
        }

        $data = self::getRrdData($start, $step, $metric);
        $data = array_map(function($d) use ($date_format) {
            $date = gmdate($date_format, $d[0]);
            return $date.','.$d[1];
        }, $data);
        array_unshift($data, 'Date,Crash Reports');
        $data = implode(PHP_EOL, $data).PHP_EOL;

        \apcu_add($key, $data, 300);
        return $data;
    }
}

