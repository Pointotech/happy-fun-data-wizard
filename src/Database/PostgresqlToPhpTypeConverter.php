<?php

namespace Pointotech\Database;

use Pointotech\Code\PhpTypes;
use Pointotech\Code\SqlToPhpTypeConversionUtilities;

class PostgresqlToPhpTypeConverter implements SqlToPhpTypeConverter
{
    static function convert(string $sqlType): string
    {
        return SqlToPhpTypeConversionUtilities::switchWithRegularExpression(
            $sqlType,
            [
                [
                    'condition' => [
                        '/^bigint$/',
                        '/^integer$/',
                    ],
                    'return' => PhpTypes::int_,
                ],
                [
                    'condition' => [
                        '/^boolean$/',
                    ],
                    'return' => PhpTypes::boolean_,
                ],
                [
                    'condition' => [
                        '/^character varying$/',
                    ],
                    'return' =>  PhpTypes::string_,
                ],
                [
                    'condition' => [],
                    'return' => PhpTypes::float_,
                ],
            ],
            'Unexpected SQL type: "' . $sqlType . '".'
        );
    }
}
