<?php

namespace Pointotech\Models;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;
use Pointotech\Collections\Dictionary;
use Pointotech\Database\ColumnSorter;
use Pointotech\Database\DatabaseClient;
use Pointotech\Entities\PropertyName;
use Pointotech\Words\WordSplitter;

class PropertyNamesGenerator
{
  static function generate(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    array $tableColumns
  ): void {

    $entityName = WordSplitter::splitIntoWordsAndConvertToCamelCaseAndMakeLastWordSingular(
      $projectDirectoryPath,
      $tableName
    );

    $outputDirectory = OutputDirectory::get(
      $projectDirectoryPath,
      $entityName
    );

    $className = $entityName . 'PropertyName';

    $outputFileName = $outputDirectory . "/$className.php";

    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    file_put_contents($outputFileName, '<?php

namespace ' . $outputConfig->rootNamespace() . '\\' . $entityName . ';

class ' . $className . '
{
  ' . self::renderClassMembers($projectDirectoryPath, $tableColumns) . '
}
');
  }

  static function generateDatabaseColumnNamesByPropertyName(
    string $projectDirectoryPath,
    DatabaseClient $database,
    string $entityName,
    array $tableColumns
  ): string {
    $renderedMembers = array_map(
      function (string $tableColumnName) use ($entityName, $projectDirectoryPath, $tableColumns): string {

        $column = Dictionary::get($tableColumns, $tableColumnName);
        $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');

        $propertyName = WordSplitter::splitIntoWordsAndConvertToCamelCaseWithoutCapitalizingFirstWord(
          $projectDirectoryPath,
          $isPrimaryKey ? 'id' : $tableColumnName
        );

        return "{$entityName}PropertyName::$propertyName => '$tableColumnName',";
      },
      ColumnSorter::getNamesAndSort($tableColumns)
    );

    return "private const databaseColumnNamesByPropertyName = [\n"
      . "    " . join("\n    ", $renderedMembers)
      . "\n  ];";
  }

  private static function renderClassMembers(
    string $projectDirectoryPath,
    array $tableColumns
  ): string {
    $renderedMembers = array_map(
      function (string $tableColumnName) use (
        $projectDirectoryPath,
        $tableColumns
      ): string {

        $propertyName = PropertyName::get(
          $projectDirectoryPath,
          $tableColumns,
          $tableColumnName
        );

        return "const $propertyName = '$propertyName';";
      },
      ColumnSorter::getNamesAndSort($tableColumns)
    );

    return join("\n\n  ", $renderedMembers);
  }
}
