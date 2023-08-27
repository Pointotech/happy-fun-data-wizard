<?php

namespace Pointotech\Schemas;

use Pointotech\Code\CodeGenerators;
use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\PhpVersion;
use Pointotech\Code\Generators\ConstantGeneratorForPhp5;
use Pointotech\Code\Generators\ConstantGeneratorForPhp7;
use Pointotech\Collections\Dictionary;
use Pointotech\Database\MysqlVersionParser;
use Pointotech\Words\WordSplitter;

class PhpSchemaGenerator
{
  static function generate(
    string $projectDirectoryPath,
    string $databaseServerVersion,
    string $databaseName,
    array $columnsByTableName,
    PhpVersion $phpVersionForGeneratedCode
  ): array {
    //echo __CLASS__ . "::" . __METHOD__ . "\n";

    self::saveSchemaToPhp(
      $projectDirectoryPath,
      $databaseServerVersion,
      $databaseName,
      $columnsByTableName,
      $phpVersionForGeneratedCode
    );

    return $columnsByTableName;
  }

  private static function saveSchemaToPhp(
    string $projectDirectoryPath,
    string $databaseServerVersion,
    string $databaseName,
    array $columnsByTableName,
    PhpVersion $phpVersionForGeneratedCode
  ): void {

    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $schemaClassName = "SchemaFor" . WordSplitter::splitIntoWordsAndConvertToCamelCase(
      $projectDirectoryPath,
      $databaseName
    );

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

use Exception;

class ' . $schemaClassName . '
{
  /**
   * @param string $tableName
   * @return array
   */
  static function getColumns($tableName)
  {
    if (array_key_exists($tableName, self::COLUMNS_BY_TABLE_NAME)) {
      return self::COLUMNS_BY_TABLE_NAME[$tableName];
    }

    throw new Exception("Table does not exist in schema: \'$tableName\'.");
  }

  /**
   * @return string[]
   */
  static function getTableNames()
  {
    return array_keys(self::COLUMNS_BY_TABLE_NAME);
  }

  ';

    $constantGenerator = ($phpVersionForGeneratedCode->majorVersionNumber() === 5
      ? ConstantGeneratorForPhp5::class
      : ConstantGeneratorForPhp7::class);

    $output .= $constantGenerator::generate(
      'COLUMNS_BY_TABLE_NAME',
      self::renderColumnsByTableName($columnsByTableName, $databaseServerVersion)
    );

    $output .= '
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . "/$schemaClassName.php";

    file_put_contents($outputFileName, $output);
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    $result = $projectDirectoryPath . '/output/src/Database';

    if (!file_exists($result)) {
      mkdir($result, recursive: true);
    }

    return realpath($result);
  }

  private static function renderColumnsByTableName(
    array $columnsByTableName,
    string $databaseServerVersion
  ): string {

    $output = "[\n";

    foreach ($columnsByTableName as $tableName => $columns) {

      $output .= self::indent . self::indent . "'$tableName' => [\n";

      foreach ($columns as $tableColumnName => $column) {

        $type = $column['type'];
        $isNullable = Dictionary::getOrNull($column, 'isNullable');
        $maximumLength = Dictionary::getOrNull($column, 'maximumLength');
        $default = Dictionary::getOrNull($column, 'default');
        $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');

        $output .= self::indent . self::indent . self::indent . "'$tableColumnName' => [\n";
        $output .= self::indent . self::indent .  self::indent . self::indent . "'type' => '" . CodeGenerators::escapeSingleQuotes($type) . "',\n";

        if ($isPrimaryKey) {
          $output .= self::indent . self::indent . self::indent . self::indent . "'isPrimaryKey' => " . json_encode(true) . ",\n";
        }

        if ($isNullable === true) {
          $output .= self::indent . self::indent . self::indent . self::indent . "'isNullable' => " . json_encode($isNullable) . ",\n";
        } elseif ($isNullable === false) {
          if ('timestamp' === $type && MysqlVersionParser::isLessThan5_6($databaseServerVersion)) {
            $output .= self::indent . self::indent . self::indent . self::indent . "'isNullable' => " . json_encode(false) . ",\n";
          }
        }

        if ($maximumLength) {
          $output .= self::indent . self::indent . self::indent . self::indent . "'maximumLength' => " . json_encode($maximumLength) . ",\n";
        }

        if ($default !== null) {
          $output .= self::indent . self::indent . self::indent . self::indent . "'default' => " . json_encode($default) . ",\n";
        } elseif ('timestamp' === $type && MysqlVersionParser::isLessThan5_6($databaseServerVersion)) {
          $output .= self::indent . self::indent . self::indent . self::indent . "'default' => 'CURRENT_TIMESTAMP',\n";
        }

        $output .= self::indent . self::indent . self::indent . "],\n";
      }

      $output .= self::indent . self::indent . "],\n";
    }

    $output .= self::indent . "]";

    return $output;
  }

  private const indent = '  ';
}
