<?php

namespace Pointotech\Database;

interface SqlToPhpTypeConverter
{
    static function convert(string $sqlType): string;
}
