<?php

namespace App\Controller;

use Doctrine\DBAL\Driver\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Stats extends AbstractController
{
    const METRIC_SUBMITTED = 'throttle_submitted-submitted';

    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @Route("/stats/today", name="stats_today")
     */
    public function today()
    {
        $data = $this->cache->get('stats_today', function (ItemInterface $item) {
            $item->expiresAfter(300);

            $historical = $this->getRawRrdData('-24hours', 300, self::METRIC_SUBMITTED);
            $historical = array_reduce($historical, function ($r, $d) {
                return $r + $d[1];
            }, 0);

            return round($historical);
        });

        return new \Symfony\Component\HttpFoundation\Response($data, 200, array(
            'Content-Type' => 'text/plain',
        ));
    }

    /**
     * @Route("/stats/lifetime", name="stats_lifetime")
     */
    public function lifetime(\Redis $redis)
    {
        $data = $this->cache->get('stats_lifetime', function (ItemInterface $item) use ($redis) {
            $item->expiresAfter(10);

            return $redis->hGet('throttle:stats', 'crashes:submitted');
        });

        return new \Symfony\Component\HttpFoundation\Response($data, 200, array(
            'Content-Type' => 'text/plain',
        ));
    }

    /**
     * @Route("/stats/unique", name="stats_unique")
     */
    public function unique(Connection $db)
    {
        $data = $this->cache->get('stats_unique', function (ItemInterface $item) use ($db) {
            $item->expiresAfter(10);

            $query = $db->executeQuery('SELECT COUNT(*) AS count FROM (SELECT DISTINCT cmdline FROM crash WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS _');
            return $query->fetchColumn(0);
        });

        return new \Symfony\Component\HttpFoundation\Response($data, 200, array(
            'Content-Type' => 'text/plain',
        ));
    }

    /**
     * @Route("/stats/daily/{module}/{function}", name="stats_daily")
     */
    public function daily(Connection $db, $module = null, $function = null)
    {
        if ($module === null && $function === null) {
            $output = $this->getCsvRrdData('-90days', 86400, self::METRIC_SUBMITTED);

            return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
                'Content-Type' => 'text/csv',
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
        for ($i = 89; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-'.$i.' days'));
            $count = array_key_exists($date, $data) ? $data[$date] : 0;
            $output .= $date.','.$count.PHP_EOL;
        }

        return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
            'Content-Type' => 'text/csv',
        ));
    }

    /**
     * @Route("/stats/hourly/{module}/{function}", name="stats_hourly")
     */
    public function hourly(Connection $db, $module = null, $function = null)
    {
        if ($module === null && $function === null) {
            $output = $this->getCsvRrdData('-7days', 3600, self::METRIC_SUBMITTED);

            return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
                'Content-Type' => 'text/csv',
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
        for ($i = 167; $i >= 0; $i--) {
            $date = date('Y-m-d-G', strtotime('-'.$i.' hours'));
            $count = array_key_exists($date, $data) ? $data[$date] : 0;
            $output .= $date.','.$count.PHP_EOL;
        }

        return new \Symfony\Component\HttpFoundation\Response($output, 200, array(
            'Content-Type' => 'text/csv',
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

        return $this->json($output);
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

        return $this->json($output);
    }

    /**
     * @Route("/stats/{module}/{function}", name="stats")
     */
    public function index($module = null, $function = null)
    {
        return $this->render('stats.html.twig', array(
            'module' => $module,
            'function' => $function,
        ));
    }

    private function getRawRrdData($start, $step, $metric)
    {
        $key = 'stats_rrd_'.md5($start.$step.$metric);

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $step, $metric) {
            $item->expiresAfter(300);

            $process = (new Process([
                '/usr/bin/rrdtool',
                'graph',
                '-',
                '--start', $start,
                '--step', $step,
                '--imgformat', 'CSV',
                'DEF:value=/var/lib/munin/fennec/fennec-'.$metric.'-d.rrd:42:AVERAGE',
                'LINE1:value#000000:value',
            ]))->setTimeout(5);

            $process->mustRun();

            $data = $process->getOutput();
            $data = preg_split('/\r?\n/', trim($data));

            array_shift($data);

            $data = array_map(function($d) use ($step) {
                [$time, $value] = str_getcsv($d);

                $time = (int)$time - $step;

                $value = round((float)$value * $step);

                return [$time, $value];
            }, $data);

            return $data;
        });
    }

    private function getRrdData($start, $step, $metric)
    {
        $data = $this->getRawRrdData($start, $step, $metric);

        if (!empty($data)) {
            $last_stamp = end($data)[0] + $step;

            // TODO: Iteratively step down the periods, required for more than one day.
            $last_period = $this->getRawRrdData($last_stamp, 300, $metric);
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

    private function getCsvRrdData($start, $step, $metric)
    {
        $key = 'stats_rrd_'.md5($start.$step.$metric).'_csv';

        return $this->cache->get($key, function (ItemInterface $item) use ($start, $step, $metric) {
            $item->expiresAfter(300);

            $date_format = 'Y-m-d-H-i-s';
            if ($step >= 86400) {
                $date_format = 'Y-m-d';
            } else if ($step >= 3600) {
                $date_format = 'Y-m-d-H';
            } else if ($step >= 60) {
                $date_format = 'Y-m-d-H-i';
            }

            $data = $this->getRrdData($start, $step, $metric);
            $data = array_map(function($d) use ($date_format) {
                $date = gmdate($date_format, $d[0]);
                return $date.','.$d[1];
            }, $data);

            array_unshift($data, 'Date,Crash Reports');
            return implode(PHP_EOL, $data).PHP_EOL;
        });
    }
}

