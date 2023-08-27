<?php

namespace Pointotech\Code;

use Exception;

use Pointotech\Collections\Dictionary;
use Pointotech\Database\DatabaseClient;

class SqlToPhpTypeConversionUtilities
{
    static function getValueRangeCondition(
        string $variableName,
        array|null $valueRange,
        bool $isNullable
    ): string|null {
        if ($valueRange === null) {
            return null;
        } else {
            $options = Dictionary::getOrNull($valueRange, 'options');
            return ($isNullable ? '(is_null(' . $variableName . ') || ' : '')
                . 'in_array(' . $variableName . ', ' . self::renderListToPhpOnSingleLine($options) . ')'
                . ($isNullable ? ')' : '');
        }
    }

    static function getValueRangeDisplay(array $valueRange): string|null
    {
        $options = Dictionary::getOrNull($valueRange, 'options');
        return self::renderListToPhpOnSingleLine($options);
    }

    static function renderListToPhpOnSingleLine(array $list): string
    {
        return '[' . join(
            ', ',
            array_map(
                function ($listItem): string {
                    if (is_string($listItem)) {
                        return '\'' . CodeGenerators::escapeSingleQuotes($listItem) . '\'';
                    } else {
                        throw new Exception('Not a string: ' . var_export($listItem, return: true));
                    }
                },
                $list
            )
        ) . ']';
    }

    static function getValueRangeFromSqlType(string $sqlType): array|null
    {
        $matches = [];
        if (preg_match('/^enum\\((.+)\\)$/', $sqlType, $matches)) {
            $optionsText = $matches[1];
            $optionsParts = explode(',', $optionsText);
            $options = array_map(
                function (string $optionsPart): string {
                    $partMatches = [];
                    if (preg_match('/^\'(.*)\'$/', $optionsPart, $partMatches)) {
                        return $partMatches[1];
                    } else {
                        throw new Exception('Unexpected format for options part: "' . $optionsPart . '".');
                    }
                },
                $optionsParts
            );
            return ['options' => $options];
        }

        return null;
    }

    static function switchWithRegularExpression(string $input, array $cases, string $defaultCaseErrorMessage): string
    {
        foreach ($cases as $case) {
            $condition = Dictionary::get($case, 'condition');
            $return = Dictionary::get($case, 'return');
            if (is_array($condition)) {
                foreach ($condition as $casePattern) {
                    if (preg_match($casePattern, $input)) {
                        return $return;
                    }
                }
            } elseif (is_string($condition)) {
                if (preg_match($condition, $input)) {
                    return $return;
                }
            } else {
                throw new Exception('Not a string or an array: ' . var_export($condition, return: true));
            }
        }
        throw new Exception($defaultCaseErrorMessage);
    }

    static function convertDatabaseDefaultStringToPhpDefaultValue(
        DatabaseClient $database,
        string $sqlType,
        string $databaseDefaultString
    ): string|int|float {

        $phpType = $database->sqlToPhpTypeConverter()->convert($sqlType);

        switch ($phpType) {
            case PhpTypes::boolean_:
                return boolval($databaseDefaultString);
            case PhpTypes::string_:
                return $databaseDefaultString;
            case PhpTypes::int_:
                return intval($databaseDefaultString);
            case PhpTypes::float_:
                return floatval($databaseDefaultString);
            case PhpTypes::enum:
                return $databaseDefaultString;
            default:
                throw new Exception('Unexpected PHP type: "' . $phpType . '".');
        }
    }

    static function getValidationFunctionForPhpType(string $phpType): string
    {
        switch ($phpType) {
            case PhpTypes::enum:
            case PhpTypes::string_:
                return 'is_string';
            case PhpTypes::int_:
                return 'is_int';
            case PhpTypes::float_:
                return 'is_float';
            default:
                throw new Exception('Unexpected PHP type: "' . $phpType . '".');
        }
    }

    static function getValidationExpressionForPhpVariable(
        string $variableName,
        string $phpType,
        bool $isNullable
    ): string {
        $result = ($isNullable ? 'is_null($' . $variableName . ') || ' : '')
            . self::getValidationFunctionForPhpType($phpType)
            . '($' . $variableName . ')';

        if ($phpType === PhpTypes::float_) {
            $result .= ' || is_integer($' . $variableName . ')';
        }

        return $result;
    }
}
