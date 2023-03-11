<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('wrap', [$this, 'wrapTag'], [
                'pre_escape' => 'html',
                'is_safe' => ['html'],
            ]),
        ];
    }

    public function wrapTag(string $child, string $tag): string
    {
        return sprintf('<%s>%s</%s>', $tag, $child, $tag);
    }
}
