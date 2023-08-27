<?php

namespace Pointotech\Entities;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;
use Pointotech\Code\PhpReservedWords;
use Pointotech\Code\PhpTypes;
use Pointotech\Collections\Dictionary;
use Pointotech\Database\ColumnSorter;
use Pointotech\Database\DatabaseClient;
use Pointotech\Words\WordSplitter;

class CodeIgniterEntityGenerator
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

    $outputDirectory = OutputDirectory::get(
      $projectDirectoryPath,
      $entityName
    );
    $outputFileName = $outputDirectory . '/' . $entityName . 'Entity.php';

    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    file_put_contents($outputFileName, '<?php

namespace ' . $outputConfig->rootNamespace() . '\\' . $entityName . ';

class ' . $entityName . '
{
  ' . self::generateProperties(
      $projectDirectoryPath,
      $database,
      $entityName,
      $tableColumns
    ) . '
  function __construct(' . self::generateConstructorParameters(
      $projectDirectoryPath,
      $database,
      $tableColumns
    ) . ')
  {
    ' . self::generateConstructorParameterAssignmentStatements($projectDirectoryPath, $database, $tableName, $tableColumns) . '
  }
}
');
  }

  private static function generateConstructorParameters(
    string $projectDirectoryPath,
    DatabaseClient $database,
    array $tableColumns
  ): string {

    return "\n    " . join(
      ',
    ',
      array_map(
        function (string $tableColumnName) use ($database, $projectDirectoryPath, $tableColumns): string {

          $propertyName = PropertyName::get(
            $projectDirectoryPath,
            $tableColumns,
            $tableColumnName
          );

          return self::getPhpPropertyType(
            $database,
            $tableColumns,
            $tableColumnName
          )
            . ' $' . $propertyName;
        },
        ColumnSorter::getNamesAndSort($tableColumns)
      )
    );
  }

  private static function getPhpPropertyType(
    DatabaseClient $database,
    array $columns,
    string $columnName
  ): string {
    $column = Dictionary::get($columns, $columnName);
    $phpType = $database->sqlToPhpTypeConverter()->convert(
      Dictionary::get($column, 'type')
    );
    return ($phpType === PhpTypes::enum ? 'string' : $phpType)
      . (Dictionary::getOrNull($column, 'isNullable') ? '|null' : '');
  }

  private static function generateConstructorParameterAssignmentStatements(
    string $projectDirectoryPath,
    DatabaseClient $database,
    string $tableName,
    array $tableColumns
  ): string {

    return join(
      '
    ',
      array_map(
        function (string $tableColumnName) use ($projectDirectoryPath, $tableName, $tableColumns): string {
          $column = Dictionary::get($tableColumns, $tableColumnName);

          $propertyName = PropertyName::get(
            $projectDirectoryPath,
            $tableColumns,
            $tableColumnName
          );

          return '$this->_' . $propertyName . ' = $' . $propertyName . ';' . self::renderRelationConstructorLinesForColumn($projectDirectoryPath, $tableName, $tableColumnName, $column);
        },
        ColumnSorter::getNamesAndSort($tableColumns)
      )
    );
  }

  private static function generateProperties(
    string $projectDirectoryPath,
    DatabaseClient $database,
    string $entityName,
    array $tableColumns
  ): string {

    return join(
      '
  ',
      array_map(
        function (string $tableColumnName) use ($database, $projectDirectoryPath, $tableColumns): string {
          $column = Dictionary::get($tableColumns, $tableColumnName);
          $relationship = Dictionary::getOrNull($column, 'relationship');

          $propertyType = self::getPhpPropertyType(
            $database,
            $tableColumns,
            $tableColumnName
          );

          $propertyName = PropertyName::get(
            $projectDirectoryPath,
            $tableColumns,
            $tableColumnName
          );

          return self::renderRelationMembersForColumn($relationship) . 'function ' . PhpReservedWords::escapeFunctionName($propertyName) . '(): ' . $propertyType . '
  {
    return $this->_' . $propertyName . ';
  }
  private $_' . $propertyName . ';
';
        },
        ColumnSorter::getNamesAndSort($tableColumns)
      )
    );
  }

  private static function renderRelationMembersForColumn(?array $relation): string
  {
    if ($relation) {
      $relationName = Dictionary::get($relation, 'relationName');

      return 'function ' . PhpReservedWords::escapeFunctionName($relationName) . '() {
    return $this->_' . $relationName . ';
  }

  private $_' . $relationName . ';

';
    } else {
      return '';
    }
  }

  private static function renderRelationConstructorLinesForColumn(string $projectDirectoryPath, string $tableName, string $tableColumnName, array $column): string
  {
    $relation = Dictionary::getOrNull($column, 'relationship');

    if ($relation) {
      $relationName = Dictionary::get($relation, 'relationName');
      $relationTable = Dictionary::get($relation, 'table');

      $relatedEntityName = WordSplitter::splitIntoWordsAndConvertToCamelCaseAndMakeLastWordSingular(
        $projectDirectoryPath,
        $relationTable
      );

      return '
        $this->_' . $relationName . ' = ' . $relatedEntityName . 'Model::get($this->' . PhpReservedWords::escapeFunctionName($tableColumnName) . '());';
    } else {
      return '';
    }
  }
}
