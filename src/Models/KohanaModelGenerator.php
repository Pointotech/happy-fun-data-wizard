<?php

namespace Pointotech\Models;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\PhpReservedWords;
use Pointotech\Code\PhpTypes;
use Pointotech\Collections\Dictionary;
use Pointotech\Collections\List_;
use Pointotech\Database\DatabaseClient;
use Pointotech\Database\MysqlReservedWords;
use Pointotech\Words\WordCasing;
use Pointotech\Words\WordSplitter;

class KohanaModelGenerator
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

use Exception;

use ' . $outputConfig->rootNamespace() . '\\Database\\DatabaseClient;

class ' . $entityName . 'Model
{
  ' . self::generateTableName($tableName) . '

  ' . self::generateAllQuery($entityName) . '

  ' . self::generateBuilder($entityName) . '

  ' . self::generateDelete($entityName, $tableColumns) . '

  ' . self::generateGetByPrimaryKey($database, $entityName, $tableColumns) . '

  ' . self::generateInsert($entityName, $tableColumns) . '

  ' . self::generateUpdate($entityName, $tableColumns) . '

  ' . self::generateWhere($entityName) . '

  ' . self::generateParseRow($database, $entityName, $tableColumns) . '

  ' . self::generateInsertFunctions($entityName, $tableColumns) . '

  ' . self::generateUpdateFunctions($entityName, $tableColumns) . '

  ' . self::generateColumnParsers($database, $tableColumns) . '

  ' . self::generateConstructor() . '
}
');
  }

  /**
   * @return string[]
   */
  private static function findPrimaryKeyColumnNames(array $tableColumns): array
  {
    return List_::filter(
      array_keys($tableColumns),
      function (string $tableColumnName) use ($tableColumns): bool {
        $column = $tableColumns[$tableColumnName];
        return !!Dictionary::getOrNull($column, 'isPrimaryKey');
      }
    );
  }

  private static function generateTableName(string $tableName): string
  {
    return '/**
   * @internal
   */
  const TABLE_NAME = \'' . $tableName . '\';';
  }

  private static function generateAllQuery(string $entityName): string
  {
    return '/**
   * @return ' . $entityName . 'Entity[]
   */
  static function all()
  {
    $rows = (new DatabaseClient())->get(
      \'select * from \' . self::TABLE_NAME,
      [],
      self::TABLE_NAME,
      []
    );

    return array_map(
      /**
       * @param array $row
       * @return ' . $entityName . 'Entity
       */
      function ($row) {
        return self::parseRow($row);
      },
      $rows
    );
  }';
  }

  private static function generateBuilder(string $entityName): string
  {
    return '/**
   * @return ' . $entityName . 'EntityBuilder
   */
  static function builder()
  {
    return new ' . $entityName . 'EntityBuilder();
  }';
  }

  private static function generateDelete(string $entityName, array $tableColumns): string
  {
    $primaryKeyColumnNames = self::findPrimaryKeyColumnNames($tableColumns);

    return '/**
   * @return void
   */
  static function delete(' . $entityName . 'Entity $entity)
  {
    (new DatabaseClient())->delete(
      \'delete from \' . self::TABLE_NAME . \' where ' . join(
      ' and ',
      array_map(
        function (string $primaryKeyColumnName): string {
          return MysqlReservedWords::quoteColumnName($primaryKeyColumnName) . ' = ?';
        },
        $primaryKeyColumnNames
      )
    ) . '\',
      [
        ' . join(
      '
        ',
      array_map(
        function (string $primaryKeyColumnName): string {
          return '$entity->' . PhpReservedWords::escapeFunctionName($primaryKeyColumnName) . '(),';
        },
        $primaryKeyColumnNames
      )
    ) . '
      ],
      self::TABLE_NAME,
      [
        ' . join(
      '
        ',
      array_map(
        function (string $primaryKeyColumnName): string {
          return '"' . $primaryKeyColumnName . '" => $entity->' . PhpReservedWords::escapeFunctionName($primaryKeyColumnName) . '(),';
        },
        $primaryKeyColumnNames
      )
    ) . '
      ]
    );
  }';
  }

  private static function generateGetByPrimaryKey(
    DatabaseClient $database,
    string $entityName,
    array $tableColumns
  ): string {
    $primaryKeyColumnNames = self::findPrimaryKeyColumnNames($tableColumns);

    return '/**
   * ' . join(
      '
   * ',
      array_map(
        function (string $primaryKeyColumnName) use ($database, $tableColumns): string {
          return '@param ' . $database->sqlToPhpTypeConverter()->convert($tableColumns[$primaryKeyColumnName]['type'])  . ' $' . $primaryKeyColumnName;
        },
        $primaryKeyColumnNames
      )
    ) . '
   * @return ' . $entityName . 'Entity|null $entity
   */
  static function get(' . join(
      ', ',
      array_map(
        function (string $primaryKeyColumnName): string {
          return '$' . $primaryKeyColumnName;
        },
        $primaryKeyColumnNames
      )
    ) . ')
  {
    return self::' . join(
      '->',
      array_map(
        function (string $primaryKeyColumnName): string {
          return 'where(\'' . $primaryKeyColumnName . '\', \'=\', $' . $primaryKeyColumnName . ')';
        },
        $primaryKeyColumnNames
      )
    ) . '->find();
  }';
  }

  private static function generateInsertReturnVariableAndAssignment(array $tableColumns): string
  {
    $primaryKeyColumnNames = self::findPrimaryKeyColumnNames($tableColumns);
    if (count($primaryKeyColumnNames) > 1) {
      return '';
    } elseif (count($primaryKeyColumnNames) === 1) {
      $primaryKeyColumnName = List_::get($primaryKeyColumnNames, 0);
      $column = $tableColumns[$primaryKeyColumnName];
      if (Dictionary::getOrNull($column, 'isAutoIncremented')) {
        return '$' . $primaryKeyColumnName  . ' = ';
      } else {
        return '';
      }
    } else {
      return '';
    }
  }

  private static function generateInsert(string $entityName, array $tableColumns): string
  {
    return '/**
   * @return ' . $entityName . 'Entity
   */
  static function insert(' . $entityName . 'EntityBuilder $entityBuilder)
  {
    $columnsToInsert = self::getColumnListForInsertStatement($entityBuilder);

    ' . self::generateInsertReturnVariableAndAssignment($tableColumns)  . '(new DatabaseClient())->insert(
      \'insert into \' . self::TABLE_NAME . \'(
        \' . join(\', \', $columnsToInsert) . \'
      )
      values(
        \' . join(\', \', self::getColumnPlaceholdersForInsertStatement($columnsToInsert)) . \'
      )\',
      self::getColumnValuesForInsertStatement($entityBuilder),
      self::TABLE_NAME,
      $columnsToInsert
    );
' . self::generateQueryUpdateFromDbAfterInsert($entityName, $tableColumns) . '
    return new ' . $entityName . 'Entity(
      ' . self::generateConstructorCallParametersForSave($tableColumns) . '
    );
  }';
  }

  private static function generateUpdate(string $entityName, array $tableColumns): string
  {
    $primaryKeyColumnNames = self::findPrimaryKeyColumnNames($tableColumns);

    return '/**
   * @return ' . $entityName . 'Entity
   */
  static function update(' . $entityName . 'Entity $entity)
  {
    $columnNamesAndPlaceholders = self::getColumnNamesAndPlaceholdersForUpdateStatement($entity);

    if (count($columnNamesAndPlaceholders)) {
      (new DatabaseClient())->update(
        \'update \' . self::TABLE_NAME . \' set \'
          . join(\', \', $columnNamesAndPlaceholders)
          . \' where '
      . join(
        ' and ',
        array_map(
          function (string $primaryKeyColumnName): string {
            return MysqlReservedWords::quoteColumnName($primaryKeyColumnName) . ' = ?';
          },
          $primaryKeyColumnNames
        ),
      ) . '\',
        array_merge(
          self::getColumnValuesForUpdateStatement($entity),
          [
            ' . join(
        '
                        ',
        array_map(
          function (string $primaryKeyColumnName): string {
            return '$entity->getOriginalPropertyValue(\'' . $primaryKeyColumnName . '\'),';
          },
          $primaryKeyColumnNames
        )
      ) . '
          ]
        ),
        self::TABLE_NAME,
        $columnNamesAndPlaceholders
      );' . self::generateQueryUpdateFromDbAfterUpdate($entityName, $tableColumns) . '
    }

    return $entity;
  }';
  }

  private static function generateWhere(string $entityName): string
  {
    return '/**
   * @param string $column
   * @param string $operation
   * @param string|int|null $value
   * @return ' . $entityName . 'QueryBuilder
   */
  static function where($column, $operation, $value)
  {
    return new ' . $entityName . 'QueryBuilder($column, $operation, $value);
  }';
  }

  private static function generateParseRow(
    DatabaseClient $database,
    string $entityName,
    array $tableColumns
  ): string {

    return '/**
   * @internal
   * @param array $row
   * @return ' . $entityName . 'Entity
   */
  static function parseRow($row)
  {
    return new ' . $entityName . 'Entity(
      ' . self::generateConstructorCallParametersForParseRow($database, $tableColumns) . '
    );
  }';
  }

  private static function generateInsertFunctions(string $entityName, array $tableColumns): string
  {
    return '/**
   * @return string[]
   */
  private static function getColumnListForInsertStatement(' . $entityName . 'EntityBuilder $entityBuilder)
  {
    $result = [];

    ' . self::generateContentsOfMethodGetColumnListForInsertStatement($tableColumns) . '

    return $result;
  }

  /**
   * @param string[] $columnNames
   * @return array
   */
  private static function getColumnPlaceholdersForInsertStatement(array $columnNames)
  {
    return array_map(
      /**
       * @param string $columnName
       * @return string
       */
      function ($columnName) {
        return \'?\';
      },
      $columnNames
    );
  }

  /**
   * @return array
   */
  private static function getColumnValuesForInsertStatement(' . $entityName . 'EntityBuilder $entityBuilder)
  {
    $result = [];

    ' . self::generateContentsOfMethodGetColumnValuesForInsertStatement($tableColumns) . '

    return $result;
  }';
  }

  private static function generateUpdateFunctions(string $entityName, array $tableColumns): string
  {
    return '/**
   * @return string[]
   */
  private static function getColumnNamesAndPlaceholdersForUpdateStatement(' . $entityName . 'Entity $entity)
  {
    $result = [];

    ' . self::generateContentsOfMethodGetColumnNamesAndPlaceholdersForUpdateStatement($tableColumns) . '

    return $result;
  }

  /**
   * @return array
   */
  private static function getColumnValuesForUpdateStatement(' . $entityName . 'Entity $entity)
  {
    $result = [];

    ' . self::generateContentsOfMethodGetColumnValuesForUpdateStatement($tableColumns) . '

    return $result;
  }';
  }

  private static function generateColumnParsers(
    DatabaseClient $database,
    array $tableColumns
  ): string {

    $hasFloatColumns = count(
      List_::filter(
        array_keys($tableColumns),
        function (string $columnName) use ($database, $tableColumns): bool {
          return $database->sqlToPhpTypeConverter()->convert($tableColumns[$columnName]['type']) === PhpTypes::float_;
        }
      )
    );

    $result = '/**
   * @param string $value
   * @return int
   */
  private static function parseColumnAsInt($value)
  {
    if (is_integer($value) || (((string)intval($value)) === $value)) {
      return intval($value);
    } else {
      throw new Exception(\'Column value must be an integer. Value: "\' . var_export($value, true) . \'".\');
    }
  }

  /**
   * @param string $value
   * @return int|null
   */
  private static function parseColumnAsNullableInt($value)
  {
    if (is_null($value)) {
      return null;
    } elseif (is_integer($value) || (((string)intval($value)) === $value)) {
      return intval($value);
    } else {
      throw new Exception(\'Column value must be an integer or null. Value: "\' . var_export($value, true) . \'".\');
    }
  }

  /**
   * @param string $value
   * @return string
   */
  private static function parseColumnAsString($value)
  {
    if (is_string($value)) {
      return $value;
    } else {
      throw new Exception(\'Column value must be a string. Value: "\' . var_export($value, true) . \'".\');
    }
  }

  /**
   * @param string $value
   * @return string|null
   */
  private static function parseColumnAsNullableString($value)
  {
    if (is_null($value)) {
      return null;
    } elseif (is_string($value)) {
      return $value;
    } else {
      throw new Exception(\'Column value must be a string or null. Value: "\' . var_export($value, true) . \'".\');
    }
  }';

    if ($hasFloatColumns) {
      $result .= '
    /**
     * @param string $value
     * @return float
     */
    private static function parseColumnAsFloat($value)
    {
      if (is_float($value) || (((string)floatval($value)) === self::trimTrailingDecimalZeroesFromFloatString($value)) || is_integer($value)) {
        return floatval($value);
      } else {
        throw new Exception(\'Column value must be a float. Value: "\' . var_export($value, true) . \'".\');
      }
    }

    /**
     * @param string $value
     * @return float|null
     */
    private static function parseColumnAsNullableFloat($value)
    {
      if (is_null($value)) {
        return null;
      } else {
        $result = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);

        if ($result === null) {
          throw new Exception(\'Column value must be a float or null. Value: "\' . var_export(self::trimTrailingDecimalZeroesFromFloatString($value), true) . \'".\');
        }

        return $result;
      }
    }

    /**
     * @param string $value
     * @return string
     */
    private static function trimTrailingDecimalZeroesFromFloatString($value)
    {
      if (is_null($value)) {
        return null;
      } elseif (is_string($value)) {
        return preg_replace(\'/0+$/\', \'\', $value);
      } else {
        throw new Exception(\'Parameter must be a string. Value: "\' . var_export($value, true) . \'".\');
      }
    }';
    }

    return $result;
  }

  private static function generateConstructor(): string
  {
    return 'private function __construct()
  {
  }';
  }

  private static function generateConstructorCallParametersForParseRow(
    DatabaseClient $database,
    array $tableColumns
  ): string {

    return join(
      ',
      ',
      array_map(
        function (string $tableColumnName) use ($database, $tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $phpType = $database->sqlToPhpTypeConverter()->convert($column['type']);
          $isNullable = Dictionary::getOrNull($column, 'isNullable');
          return 'self::parseColumnAs' . ($isNullable ? 'Nullable' : '') . WordCasing::capitalize(($phpType === PhpTypes::enum ? 'string' : $phpType)) . '($row[\'' . $tableColumnName . '\'])';
        },
        array_keys($tableColumns)
      )
    );
  }

  private static function generateConstructorCallParametersForSave(array $tableColumns): string
  {
    return join(
      ',
      ',
      array_map(
        function (string $tableColumnName) use ($tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $default = Dictionary::getOrNull($column, 'default');
          $sqlType = $column['type'];
          $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');
          $isAutoIncremented = Dictionary::getOrNull($column, 'isAutoIncremented');

          if ($isPrimaryKey && $isAutoIncremented) {
            return '$' . $tableColumnName;
          } elseif ($default !== null && !($sqlType === 'timestamp' && $default === 'CURRENT_TIMESTAMP')) {
            return '$entityBuilder->' . $tableColumnName . ' === null ? ' . json_encode($default) . ' : $entityBuilder->' . $tableColumnName;
          } else {
            return '$entityBuilder->' . $tableColumnName;
          }
        },
        array_keys($tableColumns)
      )
    );
  }

  private static function generateQueryUpdateFromDbAfterInsert(string $entityName, array $tableColumns): string
  {
    $tableColumnNamesWithDefaultValueGeneratedByDb = List_::filter(
      array_keys($tableColumns),
      function (string $columnName) use ($tableColumns): bool {
        $column = $tableColumns[$columnName];
        return $column['type'] === 'timestamp' && Dictionary::getOrNull($column, 'default') === 'CURRENT_TIMESTAMP';
      }
    );

    if (count($tableColumnNamesWithDefaultValueGeneratedByDb)) {
      $primaryKeyColumnNames = self::findPrimaryKeyColumnNames($tableColumns);
      $whereCalls = join(
        '
            ->',
        array_map(
          function (string $primaryKeyColumnName): string {
            return 'where(\'' . $primaryKeyColumnName . '\', \'=\', $' . $primaryKeyColumnName . ')';
          },
          $primaryKeyColumnNames
        )
      );
      return '
        $entityFromDb = ' . $entityName . 'Model::' . $whereCalls . '->find();
        ' . join(
        '
        ',
        array_map(
          function (string $columnName): string {
            return '$entityBuilder->' . $columnName . ' = $entityFromDb->' . $columnName . '();';
          },
          $tableColumnNamesWithDefaultValueGeneratedByDb
        )
      ) . '
        ';
    } else {
      return '';
    }
  }

  private static function generateQueryUpdateFromDbAfterUpdate(string $entityName, array $tableColumns): string
  {
    $tableColumnNamesWithDefaultValueGeneratedByDb = List_::filter(
      array_keys($tableColumns),
      function (string $columnName) use ($tableColumns): bool {
        $column = $tableColumns[$columnName];
        return $column['type'] === 'timestamp' && Dictionary::getOrNull($column, 'default') === 'CURRENT_TIMESTAMP';
      }
    );

    if (count($tableColumnNamesWithDefaultValueGeneratedByDb)) {
      $primaryKeyColumnNames = self::findPrimaryKeyColumnNames($tableColumns);
      $whereCalls = join(
        '
            ->',
        array_map(
          function (string $primaryKeyColumnName): string {
            return 'where(\'' . $primaryKeyColumnName . '\', \'=\', $entity->getOriginalPropertyValue(\'' . $primaryKeyColumnName . '\'))';
          },
          $primaryKeyColumnNames
        )
      );
      return '
            return ' . $entityName . 'Model::' . $whereCalls . '->find();';
    } else {
      return '';
    }
  }

  private static function generateContentsOfMethodGetColumnListForInsertStatement(array $tableColumns): string
  {
    return join(
      '
    ',
      array_map(
        function (string $tableColumnName) use ($tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $default = Dictionary::getOrNull($column, 'default');

          if ($default === null) {
            return '$result[] = \'' . MysqlReservedWords::quoteColumnName($tableColumnName) . '\';';
          } else {
            return 'if ($entityBuilder->' . $tableColumnName . ' !== null) {
      $result[] = \'' . MysqlReservedWords::quoteColumnName($tableColumnName) . '\';
    }';
          }
        },
        List_::filter(
          array_keys($tableColumns),
          function (string $tableColumnName) use ($tableColumns): bool {
            $column = $tableColumns[$tableColumnName];
            $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');
            $isAutoIncremented = Dictionary::getOrNull($column, 'isAutoIncremented');
            return !$isPrimaryKey || !$isAutoIncremented;
          }
        )
      )
    );
  }

  private static function generateContentsOfMethodGetColumnValuesForInsertStatement(array $tableColumns): string
  {
    return join(
      '
    ',
      array_map(
        function (string $tableColumnName) use ($tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $default = Dictionary::getOrNull($column, 'default');

          if ($default === null) {
            return '$result[] = $entityBuilder->' . $tableColumnName . ';';
          } else {
            return 'if ($entityBuilder->' . $tableColumnName . ' !== null) {
      $result[] = $entityBuilder->' . $tableColumnName . ';
    }';
          }
        },
        List_::filter(
          array_keys($tableColumns),
          function (string $tableColumnName) use ($tableColumns): bool {
            $column = $tableColumns[$tableColumnName];
            $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');
            $isAutoIncremented = Dictionary::getOrNull($column, 'isAutoIncremented');
            return !$isPrimaryKey || !$isAutoIncremented;
          }
        )
      )
    );
  }

  private static function generateContentsOfMethodGetColumnNamesAndPlaceholdersForUpdateStatement(array $tableColumns): string
  {
    return join(
      '
    ',
      array_map(
        function (string $tableColumnName) use ($tableColumns): string {

          $result = 'if ($entity->isPropertyDirty(\'' . $tableColumnName . '\')) {
      $result[] = \'' . MysqlReservedWords::quoteColumnName($tableColumnName) . ' = ?\';
    }';

          return $result;
        },
        List_::filter(
          array_keys($tableColumns),
          function (string $tableColumnName) use ($tableColumns): bool {
            $column = $tableColumns[$tableColumnName];
            $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');
            $isAutoIncremented = Dictionary::getOrNull($column, 'isAutoIncremented');
            return !$isPrimaryKey || !$isAutoIncremented;
          }
        )
      )
    );
  }

  private static function generateContentsOfMethodGetColumnValuesForUpdateStatement(array $tableColumns): string
  {
    return join(
      '
    ',
      array_map(
        function (string $tableColumnName) use ($tableColumns): string {

          $result = 'if ($entity->isPropertyDirty(\'' . $tableColumnName . '\')) {
      $result[] = $entity->' . PhpReservedWords::escapeFunctionName($tableColumnName) . '();
    }';

          return $result;
        },
        List_::filter(
          array_keys($tableColumns),
          function (string $tableColumnName) use ($tableColumns): bool {
            $column = $tableColumns[$tableColumnName];
            $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');
            $isAutoIncremented = Dictionary::getOrNull($column, 'isAutoIncremented');
            return !$isPrimaryKey || !$isAutoIncremented;
          }
        )
      )
    );
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
