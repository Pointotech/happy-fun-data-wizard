<?php

namespace Pointotech\Text;

use Exception;

class Strings
{
  static function cast($value): string
  {
    if (is_string($value)) {
      return $value;
    } else {
      throw new Exception('Value is not a string: ' . $value);
    }
  }

  static function castOrEmpty($value): string
  {
    if (is_string($value)) {
      return $value;
    } else {
      return '';
    }
  }

  static function castOrNull($value): ?string
  {
    if (is_string($value)) {
      return $value;
    } else {
      return null;
    }
  }

  static function toString($value): string
  {
    if (is_string($value)) {
      return $value;
    } elseif (is_int($value)) {
      return (int)$value;
    } elseif (is_float($value)) {
      return (float)$value;
    } elseif (is_null($value)) {
      return 'null';
    } else {
      throw new Exception('Value is not a string or a number or null: ' . $value);
    }
  }
}
