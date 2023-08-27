<?php

namespace Pointotech\Collections;

use Exception;

class Dictionary
{
    static function get(array $dictionary, string $key)
    {
        if (array_key_exists($key, $dictionary)) {
            return $dictionary[$key];
        } else {
            throw new Exception('Dictionary does not contain key "' . $key . '". Existing keys: ' . json_encode(array_keys($dictionary)));
        }
    }

    static function getOrNull(array $dictionary, string $key)
    {
        if (array_key_exists($key, $dictionary)) {
            return $dictionary[$key];
        } else {
            return null;
        }
    }

    static function sortByKey(array $dictionary): array
    {
        $result = self::clone($dictionary);
        ksort($result);

        return $result;
    }

    static function clone(array $dictionary): array
    {
        return array_map(
            function ($element) {
                return ((is_array($element))
                    ? self::clone($element)
                    : ((is_object($element))
                        ? clone $element
                        : $element
                    )
                );
            },
            $dictionary
        );
    }
}
