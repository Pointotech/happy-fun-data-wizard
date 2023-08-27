<?php

namespace Pointotech\Words;

class SingularWords
{
    static function find(string $singularWord): ?string
    {
        if (array_key_exists($singularWord, self::ALL)) {
            return $singularWord;
        } else {
            return null;
        }
    }

    static function getPlural(string $singularWord): string
    {
        if (array_key_exists($singularWord, self::ALL)) {
            return self::ALL[$singularWord]['plural'];
        } else {
            return $singularWord;
        }
    }

    private const ALL = [
        'analytic' => [
            'plural' => 'analytics',
        ],
        'chapter' => [
            'plural' => 'chapters',
        ],
        'click' => [
            'plural' => 'clicks',
        ],
    ];
}
