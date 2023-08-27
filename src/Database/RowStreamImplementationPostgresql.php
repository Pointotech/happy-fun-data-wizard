<?php

namespace Pointotech\Database;

use Pointotech\Code\AssertTypes;

class RowStreamImplementationPostgresql implements RowStream
{
    function next(): array|null
    {
        $result = pg_fetch_array($this->queryResult(), null, PGSQL_ASSOC);

        if ($result) {
            return $result;
        } else {
            return null;
        }
    }

    function close(): void
    {
        $this->queryResult()->free();
        $this->queryResult()->close();
        $this->connection()->close();
    }

    function __construct($queryResult, $connection)
    {
        AssertTypes::isPostgresqlConnection($connection);
        AssertTypes::isPostgresqlQueryResult($queryResult);

        $this->_queryResult = $queryResult;
        $this->_connection = $connection;
    }

    private function queryResult()
    {
        return $this->_queryResult;
    }
    private $_queryResult;

    private function connection()
    {
        return $this->_connection;
    }
    private $_connection;
}
