<?php

namespace Pointotech\Database;

use ErrorException;
use Exception;
use InvalidArgumentException;

use Pointotech\Code\AssertTypes;

class ConnectionImplementationPostgresql implements Connection
{
    function get(string $query, array $parameterValues = []): array
    {
        $query = $this->convertQueryPlaceholdersToPostgresqlSyntax($query);

        try {
            $result = pg_query_params(
                $this->connection(),
                $query,
                $parameterValues
            );
        } catch (ErrorException) {
            throw new Exception(
                'Error while running query. '
                    . 'Query: "' . $query . '". '
                    . 'Parameters: ' . json_encode($parameterValues)  . '. '
                    . 'Error: ' . pg_last_error($this->connection())
            );
        }

        if ($result === false) {
            throw new Exception(pg_last_error($this->connection()));
        }

        $rows = [];

        while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
            $rows[] = $row;
        }

        //pg_freeresult($result);

        return $rows;
    }

    function getStream(string $query, array $parameterValues = []): RowStream
    {
        if (count($parameterValues) > 0) {
            throw new InvalidArgumentException('Parameter values are not implemented for PostgreSQL.');
        }

        $query = $this->convertQueryPlaceholdersToPostgresqlSyntax($query);

        try {
            $result = pg_query($this->connection(), $query);
        } catch (ErrorException) {
            throw new Exception(
                'Error while running query. '
                    . 'Query: "' . $query . '". '
                    . 'Parameters: ' . json_encode($parameterValues)  . '. '
                    . 'Error: ' . pg_last_error($this->connection())
            );
        }

        if ($result === false) {
            throw new Exception(pg_last_error($this->connection()));
        }

        return new RowStreamImplementationPostgresql($result, $this->connection());
    }

    function __construct($connection)
    {
        AssertTypes::isPostgresqlConnection($connection);
        $this->_connection = $connection;
    }

    private function connection()
    {
        return $this->_connection;
    }
    private $_connection;

    private function convertQueryPlaceholdersToPostgresqlSyntax(string $query): string
    {
        $i = 0;
        return preg_replace_callback(
            '/= \?/',
            function () use (&$i): string {
                $i++;
                return '= $' . $i;
            },
            $query
        );
    }
}
