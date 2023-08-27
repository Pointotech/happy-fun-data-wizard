<?php

namespace Pointotech\Database;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;

class DatabaseClientConfiguredByDotEnvFileGenerator
{
  static function generate(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

use Exception;
use mysqli;

class DatabaseClient
{
  function databaseName(): string
  {
    return $this->configuration()->name();
  }

  /**
   * @param string $query
   * @param array $listOfParameterValues
   * @return void
   */
  function delete($query, $listOfParameterValues = [])
  {' . self::generateLogQueryStatement()  . '
    $mysqli = $this->getDBConnection();
    $statement = $mysqli->prepare($query);

    if ($statement === false) {
      throw new Exception($mysqli->error);
    }

    if (count($listOfParameterValues)) {
      $parameterTypeLetters = $this->buildParameterTypes($listOfParameterValues);
      $statement->bind_param(join(\'\', $parameterTypeLetters), ...$listOfParameterValues);
    }

    $result = $statement->execute();

    if (!$result) {
      throw new Exception($statement->error);
    }

    $statement->close();
    $mysqli->close();
  }

  /**
   * @param string $query
   * @param array $listOfParameterValues
   * @return array[]
   */
  function get($query, $listOfParameterValues = [])
  {' . self::generateLogQueryStatement()  . '
    $mysqli = $this->getDBConnection();
    $statement = $mysqli->prepare($query);

    if ($statement === false) {
      $errorMessage = $mysqli->error;

      if (preg_match(\'/^You have an error in your SQL syntax/\', $errorMessage)) {
        if (!preg_match(\'/[^.]$/\', $errorMessage)) {
          $errorMessage .= \'.\';
        }
        $errorMessage .= \'

Query: `\' . $query . \'`\';
      }

      throw new Exception($errorMessage);
    }

    if (count($listOfParameterValues)) {
      $parameterTypeLetters = $this->buildParameterTypes($listOfParameterValues);
      $statement->bind_param(join(\'\', $parameterTypeLetters), ...$listOfParameterValues);
    }

    $result = $statement->execute();

    if (!$result) {
      throw new Exception($statement->error);
    }

    $result = $statement->get_result();
    $rows =  $result->fetch_all(MYSQLI_ASSOC);
    $statement->close();
    $mysqli->close();
    return $rows;
  }

  /**
   * @param string $query
   * @param array $listOfParameterValues
   * @return ?int
   */
  function insert($query, $listOfParameterValues = [])
  {' . self::generateLogQueryStatement()  . '
    $mysqli = $this->getDBConnection();
    $statement = $mysqli->prepare($query);

    if ($statement === false) {
      throw new Exception($mysqli->error);
    }

    if (count($listOfParameterValues)) {
      $parameterTypeLetters = $this->buildParameterTypes($listOfParameterValues);
      $statement->bind_param(join(\'\', $parameterTypeLetters), ...$listOfParameterValues);
    }

    $result = $statement->execute();

    if (!$result) {
      throw new Exception($statement->error);
    }

    $insertId = $mysqli->insert_id;

    $statement->close();
    $mysqli->close();

    return $insertId ? intval($insertId) : null;
  }

  /**
   * @param string $query
   * @param array $listOfParameterValues
   * @return void
   */
  function update($query, $listOfParameterValues = [])
  {' . self::generateLogQueryStatement()  . '
    $mysqli = $this->getDBConnection();
    $statement = $mysqli->prepare($query);

    if ($statement === false) {
      throw new Exception($mysqli->error);
    }

    if (count($listOfParameterValues)) {
      $parameterTypeLetters = $this->buildParameterTypes($listOfParameterValues);
      $bindParameterResult = $statement->bind_param(join(\'\', $parameterTypeLetters), ...$listOfParameterValues);

      if ($bindParameterResult === false) {
        throw new Exception($statement->error);
      }
    }

    $result = $statement->execute();

    if ($result === false) {
      throw new Exception($statement->error);
    }

    $statement->close();
    $mysqli->close();
  }

  private function buildParameterTypes($listOfParameterValues)
  {
    $parameterTypeLetters = [];
    foreach ($listOfParameterValues as $parameterValue) {
      if (is_string($parameterValue) || is_null($parameterValue)) {
        $parameterTypeLetters[] = \'s\';
      } elseif (is_int($parameterValue)) {
        $parameterTypeLetters[] = \'i\';
      } elseif (is_float($parameterValue)) {
        $parameterTypeLetters[] = \'d\';
      } else {
        throw new Exception("The parameter value given is not a type string, integer, or double. Value: " . var_export($parameterValue, true));
      }
    }
    return $parameterTypeLetters;
  }

  private function configuration(): DatabaseClientConfiguration
  {
    return new DatabaseClientConfigurationImplementation();
  }

  private function getDBConnection()
  {
    $configuration = $this->configuration();

    $mysqli = new mysqli(
      DatabaseServerAddressesByName::find($configuration->host()),
      $configuration->username(),
      $configuration->password(),
      $configuration->name()
    );

    $queryResult = $mysqli->query(\'set sql_mode = "STRICT_TRANS_TABLES"\');
    if ($queryResult === false) {
      throw new Exception($mysqli->error);
    }

    return $mysqli;
  }' . self::generateLogQuery() . '
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/DatabaseClient.php';

    file_put_contents($outputFileName, $output);
  }

  private const ADD_LOGGER = false;

  private static function generateLogQueryStatement(): string
  {
    if (self::ADD_LOGGER) {
      return "\n" . '        $this->logQuery($query, $listOfParameterValues);' . "\n";
    } else {
      return '';
    }
  }

  private static function generateLogQuery(): string
  {
    if (self::ADD_LOGGER) {
      return '

  /**
   * @param string $query
   * @return void
   */
  private function logQuery($query, $parameters)
  {
    file_put_contents(__DIR__ . \'/queryLog.sql\', $query . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . \'/queryLog.sql\', json_encode($parameters, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
  }';
    } else {
      return '';
    }
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    return OutputDirectory::get($projectDirectoryPath, 'Database');
  }
}
