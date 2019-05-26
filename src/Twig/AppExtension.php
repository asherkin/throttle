<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('reldate', [$this, 'filterReldate']),
            new TwigFilter('diffdate', [$this, 'filterDiffdate']),
            new TwigFilter('identicon', [$this, 'filterIdenticon']),
            new TwigFilter('crashid', [$this, 'filterCrashid']),
            new TwigFilter('format_metadata_key', [$this, 'filterFormatMetadataKey']),
            new TwigFilter('address', [$this, 'filterAddress']),
        ];
    }

    public function filterReldate($secs) {
        $r = '';

        if ($secs >= 86400) {
            $days = floor($secs / 86400);
            $secs = $secs % 86400;
            $r .= $days . ' day';
            if ($days != 1) {
                $r .= 's';
            }
            if ($secs > 0) {
                $r .= ', ';
            }
        }

        if ($secs >= 3600) {
            $hours = floor($secs / 3600);
            $secs = $secs % 3600;
            $r .= $hours . ' hour';
            if ($hours != 1) {
                $r .= 's';
            }
            if ($secs > 0) {
                $r .= ', ';
            }
        }

        if ($secs >= 60) {
            $minutes = floor($secs / 60);
            $secs = $secs % 60;
            $r .= $minutes . ' minute';
            if ($minutes != 1) {
                $r .= 's';
            }
            if ($secs > 0) {
                $r .= ', ';
            }
        }

        $r .= $secs . ' second';
        if ($secs != 1) {
            $r .= 's';
        }

        return $r;
    }

    public function filterDiffdate($ts) {
        $diff = time() - $ts;
        $day_diff = floor($diff / 86400);

        if($day_diff == 0)
        {
            if($diff < 60) return 'just now';
            if($diff < 120) return '1 minute ago';
            if($diff < 3600) return floor($diff / 60) . ' minutes ago';
            if($diff < 7200) return '1 hour ago';
            if($diff < 86400) return floor($diff / 3600) . ' hours ago';
        }

        if($day_diff == 1) return '1 day ago';
        if($day_diff < 7) return $day_diff . ' days ago';
        if($day_diff < 31) return ceil($day_diff / 7) . ' weeks ago';
        if($day_diff < 60) return '1 month ago';

        return date('F Y', $ts);
    }

    public function filterIdenticon($string, $size = 20) {
        return 'https://secure.gravatar.com/avatar/' . md5($string) . '?s=' . ($size * 2) . '&r=any&default=identicon&forcedefault=1';
    }

    public function filterCrashid($string) {
        return implode('-', str_split(strtoupper($string), 4));
    }

    public function filterFormatMetadataKey($string) {
        $name = implode(' ', array_map(function($d) {
            switch (strtolower($d)) {
            case 'url':
            case 'lsb':
            case 'pid':
            case 'guid':
            case 'id':
            case 'mvp':
                return strtoupper($d);
            default:
                return ucfirst($d);
            }
        }, preg_split('/(?:(?<=[a-z])(?=[A-Z])|_|-)/x', $string)));

        switch ($name) {
        case 'Prod':
            return 'Host Product';
        case 'Ver':
            return 'Host Version';
        case 'Rept':
            return 'Reporter';
        case 'Ptime':
            return 'Process Time';
        case 'Source Mod Path':
            return 'SourceMod Path';
        default:
            return $name;
        }
    }

    public function filterAddress($string) {
        return sprintf('0x%08s', $string);
    }
}
