<?php

namespace Pointotech\Code\Generators;

use Pointotech\Collections\Dictionary;

class DictionaryGenerator
{
  static function generate(array $dictionary): string
  {
    if (count($dictionary)) {
      return '[
    ' . join(
        '
    ',
        array_map(
          function (string $key) use ($dictionary): string {
            $value = Dictionary::get($dictionary, $key);
            return '\'' . $key . '\' => '  . json_encode($value, JSON_PRETTY_PRINT) . ',';
          },
          array_keys($dictionary)
        )
      ) . '
  ]';
    } else {
      return '[]';
    }
  }
}
