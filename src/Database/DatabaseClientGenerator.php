<?php

namespace Pointotech\Database;

use Exception;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;
use Pointotech\Json\JsonFileReader;

class DatabaseClientGenerator
{
  static function generate(string $projectDirectoryPath, string $databaseConfigurationSource): void
  {
    //echo __CLASS__ . "::" . __METHOD__ . "\n";

    self::saveDatabaseClientConfigurationToPhp($projectDirectoryPath);

    if ($databaseConfigurationSource === "dotEnvFile") {
      DatabaseClientConfigurationImplementationFromDotEnvFileGenerator::generate($projectDirectoryPath);
      DatabaseClientConfiguredByDotEnvFileGenerator::generate($projectDirectoryPath);
    } elseif ($databaseConfigurationSource === "constructor") {
      DatabaseClientConfigurationImplementationFromConstructorGenerator::generate($projectDirectoryPath);
      DatabaseClientConfiguredByConstructorGenerator::generate($projectDirectoryPath);
    } else {
      throw new Exception("The 'databaseConfigurationSource' setting must have one of these values: constructor, dotEnvFile");
    }

    self::saveDatabaseServerNamesToPhp($projectDirectoryPath);
    self::saveDatabaseServerAddressesByNameToPhp($projectDirectoryPath);
    WhereClauseGenerator::generate($projectDirectoryPath);
    OrderByClauseGenerator::generate($projectDirectoryPath);
    self::saveMysqlReservedWordsToPhp($projectDirectoryPath);
  }

  private static function saveDatabaseClientConfigurationToPhp(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

interface DatabaseClientConfiguration
{
  /**
   * @return string
   */
  function host();

  /**
   * @return string
   */
  function username();

  /**
   * @return string
   */
  function password();

  /**
   * Name of the database.
   * 
   * @return string
   */
  function name();
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/DatabaseClientConfiguration.php';

    file_put_contents($outputFileName, $output);
  }

  private static function saveDatabaseServerNamesToPhp(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $databaseServerAddressesByName = JsonFileReader::read(
      $projectDirectoryPath,
      'DatabaseServerAddressesByName.json'
    );

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

class DatabaseServerNames
{
' . join("\n\n", array_map(
      function (string $serverName) {
        return '    const ' . $serverName . ' = \'' . $serverName . '\';';
      },
      array_keys($databaseServerAddressesByName)
    )) . '
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/DatabaseServerNames.php';

    file_put_contents($outputFileName, $output);
  }

  private static function saveDatabaseServerAddressesByNameToPhp(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $databaseServerAddressesByName = JsonFileReader::read(
      $projectDirectoryPath,
      'DatabaseServerAddressesByName.json'
    );

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

use Exception;

class DatabaseServerAddressesByName
{
    /**
     * @param string $serverName
     * @return string
     */
    static function find($serverName)
    {
        if (array_key_exists($serverName, self::ALL)) {
            return self::ALL[$serverName];
        } else {
            throw new Exception("Unknown database server name: \'$serverName\'. Valid names: " . json_encode(array_keys(self::ALL)));
        }
    }

    /**
     * @internal Not actually public, but the current version of PHP only supports public constants.
     */
    const ALL = [
';

    foreach ($databaseServerAddressesByName as $serverName => $serverAddress) {
      $output .= '        DatabaseServerNames::' . $serverName . ' => \'' . $serverAddress . '\',
';
    }

    $output .= '    ];
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/DatabaseServerAddressesByName.php';

    file_put_contents($outputFileName, $output);
  }

  private static function saveMysqlReservedWordsToPhp(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

/**
 * List of words comes from https://dev.mysql.com/doc/refman/8.0/en/keywords.html .
 */
class MysqlReservedWords
{
    /**
     * @param string $columnName
     * @return string
     */
    static function quoteColumnName($columnName)
    {
        $isColumnNameReservedWord = in_array(strtoupper($columnName), self::MYSQL_8_RESERVED_WORDS)
            || in_array(strtoupper($columnName), self::MYSQL_5_BUT_NOT_MYSQL_8_RESERVED_WORDS);

        return ($isColumnNameReservedWord ? \'`\' : \'\') . $columnName . ($isColumnNameReservedWord ? \'`\' : \'\');
    }

    /**
     * @internal
     */
    const MYSQL_5_BUT_NOT_MYSQL_8_RESERVED_WORDS = [
        ' . join(
      '
        ',
      array_map(
        function (string $word): string {
          return '\'' . $word . '\',';
        },
        MysqlReservedWords::MYSQL_5_BUT_NOT_MYSQL_8_RESERVED_WORDS
      )
    ) . '
    ];

    /**
     * @internal
     */
    const MYSQL_8_RESERVED_WORDS = [
        ' . join(
      '
        ',
      array_map(
        function (string $word): string {
          return '\'' . $word . '\',';
        },
        MysqlReservedWords::MYSQL_8_RESERVED_WORDS
      )
    ) . '
    ];
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/MysqlReservedWords.php';

    file_put_contents($outputFileName, $output);
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    return OutputDirectory::get($projectDirectoryPath, 'Database');
  }
}
