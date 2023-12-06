<?php

namespace Pointotech\Database;

use Exception;
use mysqli;
use mysqli_sql_exception;

use Pointotech\Code\OperatingSystemDependencyMissing;
use Pointotech\Collections\Dictionary;
use Pointotech\Collections\List_;

class DatabaseClient
{
  function databaseName(): string
  {
    return $this->_initialDatabaseName === null ? $this->configuration()->name() : $this->_initialDatabaseName;
  }

  /**
   * @return array[]
   */
  function get(string $query, array $parameterValues = []): array
  {
    return $this->getConnection()->get($query, $parameterValues);
  }

  function getStream(string $query, array $parameterValues = []): RowStream
  {
    return $this->getConnection()->getStream($query, $parameterValues);
  }

  function getTables(string $databaseName): array
  {
    if ($this->configuration()->type() === DatabaseType::mysql()) {
      return self::getTablesMysql($this, $databaseName);
    } elseif ($this->configuration()->type() === DatabaseType::postgresql()) {
      return self::getTablesPostgresql($this, $databaseName);
    } else {
      throw new Exception('Unknown database type: ' . $this->configuration()->type()->name());
    }
  }

  function getVersion(): string
  {
    $versionRows = $this->get('select version()');

    if (count($versionRows) !== 1) {
      throw new Exception('Unexpected result from "select version()" query: ' . json_encode($versionRows));
    }

    if ($this->configuration()->type() === DatabaseType::mysql()) {
      $resultColumnName = 'version()';
    } elseif ($this->configuration()->type() === DatabaseType::postgresql()) {
      $resultColumnName = 'version';
    } else {
      throw new Exception('Unknown database type: ' . $this->configuration()->type()->name());
    }

    return Dictionary::get($versionRows[0], $resultColumnName);
  }

  function isColumnAutoIncremented(array $informationSchemaColumnRow): bool
  {
    if ($this->configuration()->type() === DatabaseType::mysql()) {
      return Dictionary::get(
        $informationSchemaColumnRow,
        $this->informationSchemaColumnNames()->extra()
      ) === 'auto_increment';
    } elseif ($this->configuration()->type() === DatabaseType::postgresql()) {
      $defaultValue = Dictionary::get(
        $informationSchemaColumnRow,
        $this->informationSchemaColumnNames()->columnDefault()
      );

      if ($defaultValue === null) {
        return false;
      } else {
        return preg_match('/^nextval\(.+\)$/', $defaultValue);
      }
    } else {
      throw new Exception('Unknown database type: ' . $this->configuration()->type()->name());
    }
  }

  function isColumnPrimaryKey(array $informationSchemaColumnRow): bool
  {
    if ($this->configuration()->type() === DatabaseType::mysql()) {
      return Dictionary::get(
        $informationSchemaColumnRow,
        $this->informationSchemaColumnNames()->columnKey()
      ) === 'PRI';
    } elseif ($this->configuration()->type() === DatabaseType::postgresql()) {
      $tableName = Dictionary::get($informationSchemaColumnRow, $this->informationSchemaColumnNames()->tableName());
      $columnName = Dictionary::get($informationSchemaColumnRow, $this->informationSchemaColumnNames()->columnName());
      $rows = $this->get(
        '
                    select
                        a.attname,
                        format_type(a.atttypid, a.atttypmod) as data_type
                    from pg_index i
                    join pg_attribute a
                        on a.attrelid = i.indrelid
                        and a.attnum = any(i.indkey)
                    where i.indrelid = \'' . $this->databaseName() . '.' . $tableName . '\'::regclass
                    and i.indisprimary
                    and a.attname = ?
                ',
        [
          $columnName,
        ]
      );
      return count($rows) > 0;
    } else {
      throw new Exception('Unknown database type: ' . $this->configuration()->type()->name());
    }
  }

  function quoteColumnName(string $columnName): string
  {
    if ($this->configuration()->type() === DatabaseType::mysql()) {
      return MysqlReservedWords::quoteColumnName($columnName);
    } elseif ($this->configuration()->type() === DatabaseType::postgresql()) {
      return $columnName;
    } else {
      throw new Exception('Unknown database type: ' . $this->configuration()->type()->name());
    }
  }

  private static function getTablesMysql(DatabaseClient $database, string $databaseName): array
  {
    $rows = $database->get(
      <<<SQL
        select
          {$database->informationSchemaColumnNames()->tableName()},
          DATA_LENGTH,
          INDEX_LENGTH
        from information_schema.tables
        where {$database->informationSchemaColumnNames()->tableSchema()} = ?
        and TABLE_TYPE = 'BASE TABLE'
      SQL,
      [
        $databaseName
      ]
    );

    $result = array_map(function ($row) use ($database) {

      $name = $row[$database->informationSchemaColumnNames()->tableName()];
      $dataLength = $row['DATA_LENGTH'];
      $indexLength = $row['INDEX_LENGTH'];

      $sizeInBytes = $dataLength + $indexLength;
      $sizeInKibibytes = $sizeInBytes / 1024;
      $sizeInMebiBytes = $sizeInKibibytes / 1024;
      $sizeInGibiBytes = $sizeInMebiBytes / 1024;

      return [
        'name' => $name,
        'sizeInBytes' => $sizeInBytes,
        'approximateSizeInKibibytes' => round($sizeInKibibytes, 1),
        'approximateSizeInMebibytes' => round($sizeInMebiBytes, 1),
        'approximateSizeInGibibytes' => round($sizeInGibiBytes, 1),
      ];
    }, $rows);

    return List_::sort(
      $result,
      function (array $a, array $b): int {
        return strcmp(
          Dictionary::get($a, 'name'),
          Dictionary::get($b, 'name')
        );
      }
    );
  }

  private static function getTablesPostgresql(DatabaseClient $database, string $databaseName): array
  {
    $rows = $database->get(
      '
                select
                    ' . $database->informationSchemaColumnNames()->tableName() . ',
                    pg_relation_size(
                        ' . $database->informationSchemaColumnNames()->tableSchema() . '
                            || \'.\'
                            || ' . $database->informationSchemaColumnNames()->tableName() . '
                    )
                from information_schema.tables
                where ' . $database->informationSchemaColumnNames()->tableSchema() . ' = ?
            ',
      [
        $databaseName
      ]
    );

    $result = array_map(function ($row) use ($database) {

      $name = $row[$database->informationSchemaColumnNames()->tableName()];
      $sizeInBytes = $row['pg_relation_size'];

      $sizeInKibibytes = $sizeInBytes / 1024;
      $sizeInMebiBytes = $sizeInKibibytes / 1024;
      $sizeInGibiBytes = $sizeInMebiBytes / 1024;

      return [
        'name' => $name,
        'sizeInBytes' => $sizeInBytes,
        'approximateSizeInKibibytes' => round($sizeInKibibytes, 1),
        'approximateSizeInMebibytes' => round($sizeInMebiBytes, 1),
        'approximateSizeInGibibytes' => round($sizeInGibiBytes, 1),
      ];
    }, $rows);

    return List_::sort(
      $result,
      function (array $a, array $b): int {
        return strcmp(
          Dictionary::get($a, 'name'),
          Dictionary::get($b, 'name')
        );
      }
    );
  }

  function informationSchemaColumnNames(): InformationSchemaColumnNames
  {
    if ($this->configuration()->type() === DatabaseType::mysql()) {
      return new InformationSchemaColumnNamesForMysql();
    } elseif ($this->configuration()->type() === DatabaseType::postgresql()) {
      return new InformationSchemaColumnNamesForPostgresql();
    } else {
      throw new Exception('Unknown database type: ' . $this->configuration()->type()->name());
    }
  }

  function sqlToPhpTypeConverter(): SqlToPhpTypeConverter
  {
    if ($this->configuration()->type() === DatabaseType::mysql()) {
      return new MysqlToPhpTypeConverter();
    } elseif ($this->configuration()->type() === DatabaseType::postgresql()) {
      return new PostgresqlToPhpTypeConverter();
    } else {
      throw new Exception('Unknown database type: ' . $this->configuration()->type()->name());
    }
  }

  function __construct(
    string $projectDirectoryPath,
    string|null $databaseName = null,
    string|null $environmentName = null
  ) {
    $this->_initialDatabaseName = $databaseName;
    $this->_projectDirectoryPath = $projectDirectoryPath;
    $this->_environmentName = $environmentName;
  }

  /**
   * @var DatabaseClientConfiguration|null
   */
  private $_configuration = null;

  private $_initialDatabaseName;

  private function projectDirectoryPath(): string
  {
    return $this->_projectDirectoryPath;
  }
  private $_projectDirectoryPath;

  private function environmentName(): string|null
  {
    return $this->_environmentName;
  }
  private $_environmentName;

  private function configuration(): DatabaseClientConfiguration
  {
    if ($this->_configuration === null) {
      $configuration = new DatabaseClientConfigurationImplementation(
        $this->projectDirectoryPath(),
        environmentName: $this->environmentName()
      );
      $this->_configuration = $configuration;
    }

    return $this->_configuration;
  }

  private function getConnection(): Connection
  {
    $configuration = $this->configuration();

    if ($configuration->type() === DatabaseType::mysql()) {
      if (!extension_loaded('mysqli')) {
        throw new OperatingSystemDependencyMissing(
          '`mysql` extension for PHP',
          "apt install php-mysql"
        );
      }

      try {
        $result = new mysqli(
          $configuration->host(),
          $configuration->username(),
          $configuration->password(),
          $this->databaseName(),
          $configuration->port()
        );
        $result->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
        $result->set_charset('utf8');
        return new ConnectionImplementationMysql($result);
      } catch (mysqli_sql_exception $error) {
        echo "$error\n\n";
        echo "Unable to connect to the database with these parameters:\n";
        echo json_encode($configuration);
        echo "\n";
        die();
      }
    } elseif ($configuration->type() === DatabaseType::postgresql()) {
      if (!extension_loaded('pgsql')) {
        throw new OperatingSystemDependencyMissing(
          '`pgsql` extension for PHP',
          "apt install php-pgsql"
        );
      }

      $host = $configuration->host();
      $dbname = $configuration->name();
      $user = $configuration->username();
      $password = $configuration->password();
      $result = pg_connect("host=$host dbname=$dbname user=$user password=$password");
      if ($result === false) {
        throw new Exception('Unable to connect.');
      }
      return new ConnectionImplementationPostgresql($result);
    } else {
      throw new Exception('Unknown database type: ' . $configuration->type()->name());
    }
  }
}
