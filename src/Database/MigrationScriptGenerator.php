<?php

namespace Pointotech\Database;

use Exception;

use Pointotech\Code\CodeGenerators;
use Pointotech\Collections\Dictionary;
use Pointotech\FileSystem\Directory;

class MigrationScriptGenerator
{
  static function generateMysql5_5To5_6KeepExistingBehaviorForTimestampColumns(
    array $migrationScriptParts,
    string $databaseServerVersion,
    string $tableName,
    array $tableColumns
  ): array {

    if (!MysqlVersionParser::isLessThan5_6($databaseServerVersion)) {
      //echo "No migration scripts to generate.";
      return $migrationScriptParts;
    }

    $columnNamesWithAmbiguousNullabilityTimestamp = array_filter(
      array_keys($tableColumns),
      function (string $columnName) use ($tableColumns): bool {
        $column = $tableColumns[$columnName];
        $type = $column['type'];
        return 'timestamp' === $type && Dictionary::getOrNull($column, 'isNullabilityImpliedByDatabaseServer');
      }
    );

    if (count($columnNamesWithAmbiguousNullabilityTimestamp)) {
      $migrationScriptParts[] = join(
        '
                ',
        array_map(
          function (string $columnNameWithAmbiguousNullabilityTimestamp) use ($tableName): string {
            return 'alter table ' . MysqlReservedWords::quoteColumnName($tableName) . '
modify column ' . MysqlReservedWords::quoteColumnName($columnNameWithAmbiguousNullabilityTimestamp) . '
timestamp not null default current_timestamp;
';
          },
          $columnNamesWithAmbiguousNullabilityTimestamp
        )
      );
    }

    $columnNamesWithAmbiguousDefaultTimestamp = array_filter(
      array_keys($tableColumns),
      function (string $columnName) use ($tableColumns): bool {
        $column = $tableColumns[$columnName];
        $type = $column['type'];
        return 'timestamp' === $type && Dictionary::getOrNull($column, 'isDefaultImpliedByDatabaseServer');
      }
    );

    if (count($columnNamesWithAmbiguousDefaultTimestamp)) {
      $migrationScriptParts[] = join(
        '
                ',
        array_map(
          function (string $columnNameWithAmbiguousDefaultTimestamp) use ($tableName): string {
            return 'alter table ' . MysqlReservedWords::quoteColumnName($tableName) . '
modify column ' . MysqlReservedWords::quoteColumnName($columnNameWithAmbiguousDefaultTimestamp) . '
timestamp not null default current_timestamp;
';
          },
          $columnNamesWithAmbiguousDefaultTimestamp
        )
      );
    }

    return $migrationScriptParts;
  }

  static function getOutputFilePath(string $projectDirectoryPath): string
  {
    $nextMigrationNumber = MigrationScriptGenerator::getNextMigrationNumber(
      $projectDirectoryPath
    );
    $outputDirectory = MigrationScriptGenerator::getOutputDirectory(
      $projectDirectoryPath
    );
    return $outputDirectory . '/' . $nextMigrationNumber
      . self::MIGRATION_FILE_SUFFIX;
  }

  private const MIGRATION_FILE_SUFFIX = '_mysql_5_5_to_5_6_keep_existing_behavior_for_timestamp_columns.sql';

  private static function getNextMigrationNumber(string $projectDirectoryPath): int
  {
    $fileNames = Directory::listFileNames(
      CodeGenerators::getShippingDirectory($projectDirectoryPath) . '/database/patch'
    );

    $matchedMigrationFiles = array_values(
      array_filter(
        $fileNames,
        function (string $fileName): bool {
          return str_ends_with($fileName, self::MIGRATION_FILE_SUFFIX);
        }
      )
    );

    if (count($matchedMigrationFiles)) {
      $lastFileName = $matchedMigrationFiles[0];
    } else {
      $lastFileName = $fileNames[count($fileNames) - 1];
    }

    $matches = [];
    if (!preg_match('/^(\\d+)\\D/', $lastFileName, $matches)) {
      throw new Exception('Existing migration file name does not match expected pattern: "' . $lastFileName . '".');
    }
    $lastFileNameMigrationNumber = intval($matches[1]);

    if (count($matchedMigrationFiles)) {
      return $lastFileNameMigrationNumber;
    } else {
      return $lastFileNameMigrationNumber + 1;
    }
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    $result =  $projectDirectoryPath . '/output/database/patch';

    if (!file_exists($result)) {
      mkdir($result, recursive: true);
    }

    return realpath($result);
  }
}
