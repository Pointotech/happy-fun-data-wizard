<?php

namespace Pointotech\CommandLine;

use Exception;

class CommandLineParameters
{
    static function getFirst(string $parameterExplanation = 'First parameter must be specified.'): string
    {
        global $argv;

        if (count($argv) < 2) {
            throw new Exception(
                "$parameterExplanation No parameters were specified."
            );
        }

        $result = $argv[1];

        if (!($result && is_string($result))) {
            throw new Exception("$parameterExplanation The first parameter is not a string.");
        }

        return $result;
    }
}
