<?php

namespace Pointotech\Database;

use Exception;
use InvalidArgumentException;
use mysqli;
use mysqli_sql_exception;

class ConnectionImplementationMysql implements Connection
{
    function get(string $query, array $parameterValues = []): array
    {
        try {
            $statement = $this->mysqli()->prepare($query);
        } catch (mysqli_sql_exception $error) {
            throw new Exception(
                $error->getMessage() . ".\n\nQuery: '" . trim($query) . "'.\n"
                    . "Parameters: " . json_encode($parameterValues, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
                    . "\n\n"
            );
        }

        if ($statement === false) {
            throw new Exception($this->mysqli()->error);
        }

        if (count($parameterValues)) {
            $parameterTypeLetters = $this->buildParameterTypeLetters($parameterValues);
            $statement->bind_param(join('', $parameterTypeLetters), ...$parameterValues);
        }

        $success = $statement->execute();

        if ($success === false) {
            throw new Exception($this->mysqli()->error);
        }

        $result = $statement->get_result();

        if ($result === false) {
            throw new Exception($statement->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }

    function getStream(string $query, array $parameterValues = []): RowStream
    {
        if (count($parameterValues) > 0) {
            throw new InvalidArgumentException('Parameter values are not implemented for streams.');
        }

        /*
        $statement = $mysqli->prepare($query);

        if ($statement === false) {
            throw new Exception($mysqli->error);
        }

        if (count($parameterValues)) {
            $parameterTypeLetters = $this->buildParameterTypeLetters($parameterValues);
            $statement->bind_param(join('', $parameterTypeLetters), ...$parameterValues);
        }
        */

        $result = $this->mysqli()->query($query, result_mode: MYSQLI_USE_RESULT);

        if ($result === false) {
            throw new Exception($this->mysqli()->error);
        }

        return new RowStreamImplementationMysql($result, $this->mysqli());
    }

    function __construct(mysqli $mysqli)
    {
        $this->_mysqli = $mysqli;
    }

    private function mysqli(): mysqli
    {
        return $this->_mysqli;
    }
    private $_mysqli;

    private function buildParameterTypeLetters(array $parameterValues): array
    {
        $result = [];
        foreach ($parameterValues as $parameterValue) {
            if (is_string($parameterValue) || is_null($parameterValue)) {
                $result[] = 's';
            } elseif (is_int($parameterValue)) {
                $result[] = 'i';
            } elseif (is_float($parameterValue)) {
                $result[] = 'd';
            } else {
                throw new Exception("Parameter value has a type that cannot be handled: " . var_export($parameterValue, true));
            }
        }
        return $result;
    }
}
