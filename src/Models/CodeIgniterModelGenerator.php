<?php

namespace Pointotech\Models;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Database\DatabaseClient;
use Pointotech\Words\WordSplitter;

class CodeIgniterModelGenerator
{
  static function generate(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    array $tableColumns
  ): void {

    $database = new DatabaseClient($projectDirectoryPath, $databaseName);

    $entityName = WordSplitter::splitIntoWordsAndConvertToCamelCaseAndMakeLastWordSingular(
      $projectDirectoryPath,
      $tableName
    );

    $outputDirectory = self::getOutputDirectory(
      $projectDirectoryPath,
      $entityName
    );
    $outputFileName = $outputDirectory . '/' . $entityName . 'Model.php';

    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    file_put_contents($outputFileName, '<?php

namespace ' . $outputConfig->rootNamespace() . '\\' . $entityName . ';

class ' . $entityName . 'Model
{
  ' . PropertyNamesGenerator::generateDatabaseColumnNamesByPropertyName(
      $projectDirectoryPath,
      $database,
      $entityName,
      $tableColumns
    ) . '
}
');
  }

  private static function getOutputDirectory(
    string $projectDirectoryPath,
    string $namespace
  ): string {

    $result =  $projectDirectoryPath . '/output/src/' . $namespace;

    if (!file_exists($result)) {
      mkdir($result, recursive: true);
    }

    return realpath($result);
  }
}
