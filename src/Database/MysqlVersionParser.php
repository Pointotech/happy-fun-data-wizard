<?php

namespace Pointotech\Database;

use Exception;

class MysqlVersionParser
{
    static function isLessThan5_6(string $versionFromSelectVersionQuery): bool
    {
        $matches = [];
        $isMatch = preg_match('/^(\d+)[.](\d+)[.](\d+)[^\d]*.*$/', $versionFromSelectVersionQuery, $matches);

        if (!$isMatch) {
            throw new Exception('Unable to extract MySQL version from version string: "' . $versionFromSelectVersionQuery . '".');
        }

        $majorVersion = intval($matches[1]);
        $minorVersion = intval($matches[2]);

        return $majorVersion < 5 || ($majorVersion === 5 && $minorVersion < 6);
    }
}
