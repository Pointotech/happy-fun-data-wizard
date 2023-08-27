<?php

namespace Pointotech\Code;

use PgSql\Connection;
use PgSql\Result;

class AssertTypes
{
    static function isPostgresqlConnection(Connection $connection): void
    {
    }

    static function isPostgresqlQueryResult(Result $result): void
    {
    }
}
