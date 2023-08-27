<?php

namespace Pointotech\Database;

use Pointotech\Code\PhpTypes;
use Pointotech\Code\SqlToPhpTypeConversionUtilities;

class MysqlToPhpTypeConverter implements SqlToPhpTypeConverter
{
  static function convert(string $sqlType): string
  {
    return SqlToPhpTypeConversionUtilities::switchWithRegularExpression(
      $sqlType,
      [
        [
          'condition' => [
            '/^bit\\(/',
            '/^bigint$/',
            '/^bigint\\(/',
            '/^bigint unsigned$/',
            '/^int$/',
            '/^int\\(/',
            '/^int unsigned$/',
            '/^smallint\\(/',
            '/^tinyint$/',
            '/^tinyint\\(/',
            '/^tinyint unsigned$/',
          ],
          'return' => PhpTypes::int_,
        ],
        [
          'condition' => [
            '/^blob$/',
            '/^char\\(/',
            '/^date$/',
            '/^datetime$/',
            '/^datetime\\(/',
            '/^longblob$/',
            '/^longtext$/',
            '/^mediumblob$/',
            '/^mediumtext$/',
            '/^set\\(/',
            '/^time$/',
            '/^time\\(/',
            '/^text$/',
            '/^timestamp$/',
            '/^timestamp\\(/',
            '/^varchar\\(/',
          ],
          'return' =>  PhpTypes::string_,
        ],
        [
          'condition' => [
            '/^decimal\\(/',
            '/^double$/',
            '/^double\\(/',
            '/^float$/',
            '/^float\\(/',
          ],
          'return' => PhpTypes::float_,
        ],
        [
          'condition' => '/^enum\\(/',
          'return' => PhpTypes::enum,
        ],
      ],
      'Unexpected SQL type: "' . $sqlType . '".'
    );
  }
}
