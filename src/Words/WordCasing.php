<?php

namespace Pointotech\Words;

class WordCasing
{
    static function capitalize(string $word)
    {
        if (strlen($word) > 1) {
            return strtoupper($word[0]) . strtolower(substr($word, 1));
        } elseif (strlen($word) > 0) {
            return strtoupper($word[0]);
        } else {
            return '';
        }
    }
}
