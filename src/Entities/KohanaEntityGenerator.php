<?php

namespace Pointotech\Entities;

use Pointotech\Code\CodeGenerators;
use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;
use Pointotech\Code\PhpReservedWords;
use Pointotech\Code\PhpTypes;
use Pointotech\Code\SqlToPhpTypeConversionUtilities;
use Pointotech\Collections\Dictionary;
use Pointotech\Database\DatabaseClient;
use Pointotech\Words\WordSplitter;

class KohanaEntityGenerator
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

use Exception;

class ' . $entityName . 'Entity
{
  ' . self::generateProperties($database, $entityName, $tableColumns) . '
  function __get($property)
  {
    $propertyStorageFieldName = $property;
    if (property_exists($this, $propertyStorageFieldName)) {
      return $this->$propertyStorageFieldName;
    } else {
      throw new Exception(\'Property "\' . $property . \'" does not exist.\');
    }
  }

  function __set($property, $value)
  {
    $propertyStorageFieldName = $property;
    if (property_exists($this, $propertyStorageFieldName)) {
      $this->$propertyStorageFieldName = $value;
    } else {
      throw new Exception(\'Property "\' . $property . \'" does not exist.\');
    }
  }

  /**
   * @internal
   * @param string $propertyName
   * @return bool
   */
  function isPropertyDirty($propertyName)
  {
    $propertyStorageFieldName = $propertyName;
    if (property_exists($this, $propertyStorageFieldName)) {
      return $this->$propertyStorageFieldName !== $this->originalPropertyValues[$propertyName];
    } else {
      throw new Exception(\'Property "\' . $propertyName . \'" does not exist.\');
    }
  }

  /**
   * @internal
   * @param string $propertyName
   */
  function getOriginalPropertyValue($propertyName)
  {
    $propertyStorageFieldName = $propertyName;
    if (property_exists($this, $propertyStorageFieldName)) {
      return $this->originalPropertyValues[$propertyName];
    } else {
      throw new Exception(\'Property "\' . $propertyName . \'" does not exist.\');
    }
  }

  /**
   * @return array
   */
  function as_array()
  {
    return [
      ' . self::generateAsArrayMembers($tableColumns) . '
    ];
  }

  /**
   * @return void
   */
  function delete()
  {
    ' . $entityName . 'Model::delete($this);
  }

  /**
   * @return ' . $entityName . 'Entity
   */
  function save()
  {
    return ' . $entityName . 'Model::update($this);
  }

  ' . self::generateConstructorComment($database, $tableColumns) . '
  function __construct(' . self::generateConstructorParameters($tableColumns) . ')
  {
    ' . self::generateConstructorParameterAssignmentStatements($projectDirectoryPath, $database, $tableName, $tableColumns) . '
  }

  private $originalPropertyValues = [];
}
');
  }

  private static function generateConstructorComment(
    DatabaseClient $database,
    array $tableColumns
  ): string {
    return '/**
   * ' . join(
      '
   * ',
      array_map(
        function (string $tableColumnName) use ($database, $tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $phpType = $database->sqlToPhpTypeConverter()->convert($column['type']);
          return '@param ' . ($phpType === PhpTypes::enum ? 'string' : $phpType) . (Dictionary::getOrNull($column, 'isNullable') ? '|null' : '') . ' $' . $tableColumnName;
        },
        array_keys($tableColumns)
      )
    ) . '
   */';
  }

  private static function generateConstructorParameters(array $tableColumns): string
  {
    return join(
      ', ',
      array_map(
        function (string $tableColumnName): string {
          return '$' . $tableColumnName;
        },
        array_keys($tableColumns)
      )
    );
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
        function (string $tableColumnName) use ($database, $projectDirectoryPath, $tableName, $tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $sqlType = Dictionary::get($column, 'type');
          $phpType = $database->sqlToPhpTypeConverter()->convert($sqlType);
          $isNullable = !!Dictionary::getOrNull($column, 'isNullable');
          $valueRange = SqlToPhpTypeConversionUtilities::getValueRangeFromSqlType($sqlType);
          $valueRangeCondition = SqlToPhpTypeConversionUtilities::getValueRangeCondition(
            '$' . $tableColumnName,
            $valueRange,
            $isNullable
          );
          $isValidCondition = self::getConstructorIsValidConditionString(
            $tableColumnName,
            $phpType,
            $isNullable
          );

          return 'if (!(' . $isValidCondition . ')) {
      throw new Exception(\'Parameter "' . $tableColumnName . '" must be a ' . ($phpType === PhpTypes::enum ? 'string' : $phpType) . ($isNullable ? ' or null' : '') . '. Value: \' . var_export($' . $tableColumnName . ', true) . \'.\');
    }' . ($valueRange === null ? '' : ('
    if (!' . $valueRangeCondition . ') {
      throw new Exception(\'Parameter "' . $tableColumnName . '" must be in the range of ' . CodeGenerators::escapeSingleQuotes(SqlToPhpTypeConversionUtilities::getValueRangeDisplay($valueRange)) . '. Value: \' . var_export($' . $tableColumnName . ', true));
    }')) . '
    $this->' . $tableColumnName . ' = $' . $tableColumnName . ';
    $this->originalPropertyValues[\'' . $tableColumnName . '\'] = $' . $tableColumnName . ';' . self::renderRelationConstructorLinesForColumn($projectDirectoryPath, $tableName, $tableColumnName, $column);
        },
        array_keys($tableColumns)
      )
    );
  }

  private static function getConstructorIsValidConditionString(
    string $tableColumnName,
    string $phpType,
    bool $isNullable
  ) {
    $result = SqlToPhpTypeConversionUtilities::getValidationExpressionForPhpVariable(
      $tableColumnName,
      $phpType,
      $isNullable
    );

    if ($phpType === PhpTypes::float_) {
      $result .= ' || ((string)floatval($' . $tableColumnName . ')) === $' . $tableColumnName;
    }

    return $result;
  }

  private static function generateAsArrayMembers(array $tableColumns): string
  {
    return join(
      '
      ',
      array_map(
        function (string $tableColumnName): string {
          return '\'' . $tableColumnName . '\' => $this->' . PhpReservedWords::escapeFunctionName($tableColumnName) . '(),';
        },
        array_keys($tableColumns)
      )
    );
  }

  private static function generateProperties(
    DatabaseClient $database,
    string $entityName,
    array $tableColumns
  ): string {

    return join(
      '
  ',
      array_map(
        function (string $tableColumnName) use ($database, $entityName, $tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $sqlType = Dictionary::get($column, 'type');
          $phpType = $database->sqlToPhpTypeConverter()->convert($sqlType);
          $isNullable = !!Dictionary::getOrNull($column, 'isNullable');
          $valueRange = SqlToPhpTypeConversionUtilities::getValueRangeFromSqlType($sqlType);
          $valueRangeCondition = SqlToPhpTypeConversionUtilities::getValueRangeCondition(
            '$this->' . $tableColumnName,
            $valueRange,
            $isNullable
          );
          $isValidCondition = SqlToPhpTypeConversionUtilities::getValidationExpressionForPhpVariable(
            'this->' . $tableColumnName,
            $phpType,
            $isNullable
          );
          $relationship = Dictionary::getOrNull($column, 'relationship');

          return self::renderRelationMembersForColumn($relationship) . 'function ' . PhpReservedWords::escapeFunctionName($tableColumnName) . '()
  {
    if (!(' . $isValidCondition . ')) {
      throw new Exception(\'Property "' . $tableColumnName . '" must be a ' . ($phpType === PhpTypes::enum ? 'string' : $phpType) . ($isNullable ? ' or null' : '') . '. Someone has accessed the public property directly, and they have set it to this invalid value: \' . var_export($this->' . $tableColumnName . ', true));
    }' . ($valueRange === null ? '' : ('
    if (!' . $valueRangeCondition . ') {
      throw new Exception(\'Property "' . $tableColumnName . '" must be in the range of ' . CodeGenerators::escapeSingleQuotes(SqlToPhpTypeConversionUtilities::getValueRangeDisplay($valueRange)) . '. Someone has accessed the public property directly, and they have set it to this invalid value: \' . var_export($this->' . $tableColumnName . ', true));
    }')) . '
    return $this->' . $tableColumnName . ';
  }

  /**
   * @deprecated Use `' . $entityName . 'Entity::' . PhpReservedWords::escapeFunctionName($tableColumnName) . '()` or `' . $entityName . 'Entity::set_' . $tableColumnName . '($value)` instead.
   */
  public $' . $tableColumnName . ';

  /**
   * @param ' . ($phpType === PhpTypes::enum ? 'string' : $phpType) . (Dictionary::getOrNull($column, 'isNullable') ? '|null' : '') . ' $value
   */
  function set_' . $tableColumnName . '($value)
  {
    if (!' . ($isNullable ? '(' : '') . ($isNullable ? 'is_null($value) || ' : '') . SqlToPhpTypeConversionUtilities::getValidationFunctionForPhpType($phpType) . '($value)' . ($isNullable ? ')' : '') . ') {
      throw new Exception(\'Parameter "value" must be a ' . ($phpType === PhpTypes::enum ? 'string' : $phpType) . ($isNullable ? ' or null' : '') . '. Value: \' . var_export($value, true) . \'.\');
    }' . ($valueRange === null ? '' : ('
    if (!' . $valueRangeCondition . ') {
      throw new Exception(\'Property "' . $tableColumnName . '" must be in the range of ' . CodeGenerators::escapeSingleQuotes(SqlToPhpTypeConversionUtilities::getValueRangeDisplay($valueRange)) . '. Value: \' . var_export($value, true));
    }')) . '
    $this->' . $tableColumnName . ' = $value;
  }
';
        },
        array_keys($tableColumns)
      )
    );
  }

  private static function renderRelationMembersForColumn(?array $relation): string
  {
    if ($relation) {
      $relationName = Dictionary::get($relation, 'relationName');

      return 'function ' . PhpReservedWords::escapeFunctionName($relationName) . '() {
    return $this->' . $relationName . ';
  }

  /**
   * @deprecated Use `' . PhpReservedWords::escapeFunctionName($relationName) . '()` instead.
   */
  public $' . $relationName . ';

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
        $this->' . $relationName . ' = ' . $relatedEntityName . 'Model::get($this->' . PhpReservedWords::escapeFunctionName($tableColumnName) . '());';
    } else {
      return '';
    }
  }
}
