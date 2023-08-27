<?php

namespace Pointotech\Database;

use mysqli;
use mysqli_result;

class RowStreamImplementationMysql implements RowStream
{
    function next(): array|null
    {
        return $this->_queryResult->fetch_assoc();
    }

    function close(): void
    {
        $this->_queryResult->free();
        $this->_queryResult->close();
        $this->_connection->close();
    }

    function __construct(mysqli_result $queryResult, mysqli $connection)
    {
        $this->_queryResult = $queryResult;
        $this->_connection = $connection;
    }

    private $_queryResult;

    private $_connection;
}
