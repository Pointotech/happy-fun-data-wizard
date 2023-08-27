<?php

namespace Pointotech\Collections;

use Exception;

class List_
{
  static function castOrNull($list): ?array
  {
    if (is_null($list)) {
      return null;
    } elseif (is_array($list)) {
      return $list;
    } else {
      throw new Exception('Parameter is not an array: ' . json_encode($list));
    }
  }

  static function contains(array $list, string $key): bool
  {
    return in_array($key, $list);
  }

  static function filter(array $list, ?callable $callback): array
  {
    return array_values(array_filter($list, $callback));
  }

  static function get(array $dictionary, int $key)
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

  /**
   * @param callable $compare The comparison function must return an integer less than, equal to, or greater than zero if the first argument is considered to be respectively less than, equal to, or greater than the second.
   */
  static function sort(array $list, callable $compare): array
  {
    $result = self::clone($list);
    usort($result, $compare);

    return $result;
  }

  static function clone(array $list): array
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
      $list
    );
  }
}
