<?php

namespace Pointotech\Entities;

use Pointotech\Code\Generators\DictionaryGenerator;
use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\PhpTypes;
use Pointotech\Collections\Dictionary;
use Pointotech\Database\DatabaseClient;
use Pointotech\Words\WordSplitter;

class EntityBuilderGenerator
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
    $outputFileName = $outputDirectory . '/' . $entityName . 'EntityBuilder.php';

    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    file_put_contents($outputFileName, '<?php

namespace ' . $outputConfig->rootNamespace() . '\\' . $entityName . ';

use Exception;

' . self::generateClassComment($database, $tableColumns) . '
class ' . $entityName . 'EntityBuilder
{
  ' . self::generateProperties($tableColumns) . '
  ' . self::generateSave($entityName) . '

  ' . self::generateGetter() . '

  ' . self::generateSetter() . '

  ' . self::generateConstructor() . '

  ' . self::generateMaximumLengthByProperty($tableColumns) . '
}
');
  }

  private static function generateSave(string $entityName): string
  {
    return '/**
   * @return ' . $entityName . 'Entity
   */
  function save()
  {
    return ' . $entityName . 'Model::insert($this);
  }';
  }

  private static function generateGetter(): string
  {
    return '/**
   * @param string $propertyName
   */
  function __get($propertyName)
  {
    $propertyStorageFieldName = \'_\' . $propertyName;
    if (property_exists($this, $propertyStorageFieldName)) {
      return $this->$propertyStorageFieldName;
    } else {
      throw new Exception(\'Property "\' . $propertyName . \'" does not exist.\');
    }
  }';
  }

  private static function generateSetter(): string
  {
    return '/**
   * @param string $propertyName
   * @param string|int|float|null $value
   */
  function __set($propertyName, $value)
  {
    $propertyStorageFieldName = \'_\' . $propertyName;
    if (property_exists($this, $propertyStorageFieldName)) {
      if (array_key_exists($propertyName, self::MAXIMUM_LENGTH_BY_PROPERTY)) {
        $maximumLength = self::MAXIMUM_LENGTH_BY_PROPERTY[$propertyName];
        if (strlen((string)$value) > $maximumLength) {
          throw new Exception(\'Property "\' . $propertyName . \'" has a maximum length of \' . $maximumLength . \'. Value: \' . var_export($value, true));
        }
      }
      $this->$propertyStorageFieldName = $value;
    } else {
      throw new Exception(\'Property "\' . $propertyName . \'" does not exist.\');
    }
  }';
  }

  private static function generateConstructor(): string
  {
    return '/**
   * @internal
   */
  function __construct()
  {
  }';
  }

  private static function generateMaximumLengthByProperty(array $tableColumns): string
  {
    $maximumLengthByProperty = array_reduce(
      array_filter(
        array_keys($tableColumns),
        function (string $tableColumnName) use ($tableColumns): bool {
          $column = $tableColumns[$tableColumnName];
          $maximumLength = Dictionary::getOrNull($column, 'maximumLength');
          return $maximumLength !== null;
        }
      ),
      function (array $result, string $tableColumnName) use ($tableColumns): array {
        $column = $tableColumns[$tableColumnName];
        $maximumLength = Dictionary::getOrNull($column, 'maximumLength');
        $result[$tableColumnName] = $maximumLength;
        return $result;
      },
      []
    );

    return '/**
   * @internal
   */
  const MAXIMUM_LENGTH_BY_PROPERTY = ' . DictionaryGenerator::generate($maximumLengthByProperty) . ';';
  }

  private static function generateProperties(array $tableColumns): string
  {
    return join(
      '
  ',
      array_map(
        function (string $tableColumnName): string {
          return 'private $_' . $tableColumnName . ';
';
        },
        array_keys($tableColumns)
      )
    );
  }

  private static function generateClassComment(DatabaseClient $database, array $tableColumns): string
  {
    return '/**
' . join(
      '
',
      array_map(
        function (string $tableColumnName) use ($database, $tableColumns): string {
          $column = $tableColumns[$tableColumnName];
          $phpType = $database->sqlToPhpTypeConverter()->convert($column['type']);
          return ' * @property ' . ($phpType === PhpTypes::enum ? 'string' : $phpType) . (Dictionary::getOrNull($column, 'isNullable') ? '|null' : '') . ' $' . $tableColumnName;
        },
        array_keys($tableColumns)
      )
    ) . '
 */';
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
