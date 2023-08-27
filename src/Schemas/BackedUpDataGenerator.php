<?php

namespace Pointotech\Schemas;

use Pointotech\Collections\Dictionary;
use Pointotech\Collections\List_;
use Pointotech\Configuration\ConfigurationFileReader;
use Pointotech\Configuration\IncorrectConfiguration;
use Pointotech\Database\DatabaseClient;
use Pointotech\Json\JsonFileReader;

class BackedUpDataGenerator
{
  static function generate(
    string $projectDirectoryPath,
    array $tableNamesAndSizesByDatabaseName
  ): void {

    $startTimestamp = time();
    echo 'BackedUpDataGenerator::generate started at '
      . date(format: 'm/d/Y H:i:s', timestamp: $startTimestamp) . ' '
      . '(UTC timestamp: ' . $startTimestamp . ')...' . "\n";
    echo  "\n";
    echo "Project directory path: $projectDirectoryPath" . "\n";

    $ignoredDatabaseNames = JsonFileReader::read($projectDirectoryPath, 'IgnoredDatabaseNames.json');

    foreach ($tableNamesAndSizesByDatabaseName as $databaseName => $tables) {

      if (!List_::contains($ignoredDatabaseNames, $databaseName)) {

        $schema = CachedJsonSchemaGenerator::generate($projectDirectoryPath, $databaseName);
        $columnsByTableName = $schema[CachedJsonSchemaGenerator::SCHEMA_PROPERTY_NAME_columnsByTableName];

        $isFirst = true;

        foreach ($tables as $table) {
          if ($isFirst) {
            $isFirst = false;
          } else {
            //echo "\n";
          }

          $tableName  = Dictionary::get($table, 'name');

          //echo "Table '$databaseName'.'$tableName':\n";

          $approximateSizeInMebibytes  = Dictionary::get($table, 'approximateSizeInMebibytes');

          $tableMetadataFilePath = self::getTableMetadataFilePath(
            $projectDirectoryPath,
            $databaseName,
            $tableName,
            null
          );

          if (file_exists($tableMetadataFilePath)) {
            //echo "Checking metadata file '$tableMetadataFilePath'...\n";
            $tableMetadataText = file_get_contents($tableMetadataFilePath);
            $tableMetadata = json_decode($tableMetadataText, JSON_OBJECT_AS_ARRAY);
            $isCachedDataStillValid = time() < $tableMetadata[self::DATA_PROPERTY_NAME_dataVersion] + self::CACHE_DURATION_SECONDS;
          } else {
            echo "Didn't find metadata file '$tableMetadataFilePath'.\n";
            $isCachedDataStillValid = false;
          }

          if ($isCachedDataStillValid) {
            //echo "Keeping backed up data.\n";
          } else {
            self::backUpData(
              $projectDirectoryPath,
              $databaseName,
              $tableName,
              $columnsByTableName[$tableName],
              $approximateSizeInMebibytes
            );
          }
        }
      }
    }

    $endTimestamp = time();
    echo "\n" . 'BackedUpDataGenerator::generate finished at ' . date('m/d/Y H:i:s') . ' (UTC timestamp: ' . $endTimestamp . '). Elapsed seconds: ' . ($endTimestamp - $startTimestamp) . "\n";
  }

  private const CACHE_DURATION_SECONDS = 192 * 60 * 60;

  private const DATA_PROPERTY_NAME_dataVersion = 'dataVersion';

  private static function backUpData(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    array $tableProperties,
    float $approximateSizeInMebibytes
  ): void {
    echo "Checking size of '$databaseName'.'$tableName'...\n";

    $client = new DatabaseClient($projectDirectoryPath, $databaseName);
    $primaryKeyNames = self::getPrimaryKeyNames($client, $tableProperties);
    $countColumnExpression = count($primaryKeyNames) === 1 ? $primaryKeyNames[0] : '*';
    $rowsCountRow = $client->get(
      '
                select count(' . $countColumnExpression . ') as count
                from ' . $databaseName . '.' . $tableName . '
            '
    )[0];
    $rowsCount = Dictionary::get($rowsCountRow, 'count');

    echo "Backing up data from large table '$databaseName'.'$tableName'...\n";

    echo "Running query...\n";

    $backupCriteriaConfiguration = ConfigurationFileReader::readOrNull(
      $projectDirectoryPath,
      'BackupCriteria.json'
    );
    $backupCriteria = $backupCriteriaConfiguration
      ? self::getBackupCriteriaForTable(
        $backupCriteriaConfiguration,
        $databaseName,
        $tableName
      )
      : null;
    $selectWhere = $backupCriteria ? Dictionary::getOrNull($backupCriteria, 'selectWhere') : null;
    $sortAndGroupByColumn = $backupCriteria ? Dictionary::getOrNull($backupCriteria, 'sortAndGroupByColumn') : null;
    $sortDirection = $backupCriteria ? Dictionary::getOrNull($backupCriteria, 'sortDirection') : null;
    $groupByColumnValueToFileNameTransformation = $backupCriteria ? Dictionary::getOrNull($backupCriteria, 'groupByColumnValueToFileNameTransformation') : null;

    $rowsQueryWithoutCriteria = 'select * from ' . $databaseName . '.' . $tableName;
    $rowsQuery = $rowsQueryWithoutCriteria
      . ($selectWhere ? (' where ' . $selectWhere) : '')
      . ($sortAndGroupByColumn ? (' order by ' . $sortAndGroupByColumn) : '')
      . ($sortAndGroupByColumn && $sortDirection ? (' ' . $sortDirection) : '');

    if ($rowsQuery === $rowsQueryWithoutCriteria) {
      self::saveTableDataToFilesBySections(
        $projectDirectoryPath,
        $client,
        $databaseName,
        $tableName,
        $rowsQuery,
        $rowsCount,
        $approximateSizeInMebibytes
      );
    } else {
      self::saveTableDataToFilesByColumnValue(
        $projectDirectoryPath,
        $client,
        $databaseName,
        $tableName,
        $rowsQuery,
        $rowsCount,
        $approximateSizeInMebibytes,
        $sortAndGroupByColumn,
        $groupByColumnValueToFileNameTransformation
      );
    }


    echo "Finished backing up '$databaseName.$tableName'.\n\n";
  }

  private static function saveTableDataToFilesByColumnValue(
    string $projectDirectoryPath,
    DatabaseClient $client,
    string $databaseName,
    string $tableName,
    string $rowsQuery,
    int $rowsCount,
    float $approximateSizeInMebibytes,
    string $sortAndGroupByColumn,
    string $groupByColumnValueToFileNameTransformation
  ): void {
    $rowsStream = $client->getStream($rowsQuery);

    echo "Reading " . $rowsCount . " rows to files by value of the column '" . $sortAndGroupByColumn . "'...\n";

    $rows = [];
    $currentFileName = null;

    while ($row = $rowsStream->next()) {

      $rowColumnValue = Dictionary::get($row, $sortAndGroupByColumn);

      if ('parseDateFromDateAndTime' === $groupByColumnValueToFileNameTransformation) {
        $rowColumnValueParts = explode(' ', $rowColumnValue);
        $rowFileName = $rowColumnValueParts[0];
      } elseif ('parseDateAndHourFromDateAndTime' === $groupByColumnValueToFileNameTransformation) {
        $rowColumnValueParts = explode(' ', $rowColumnValue);
        $date = $rowColumnValueParts[0];
        $time = $rowColumnValueParts[1];
        $timeParts = explode(':', $time);
        $rowFileName = $date . '_' . $timeParts[0] . ':00:00_to_' . $timeParts[0] . ':59:59';
      } else {
        throw new IncorrectConfiguration('Unknown transformation: "' . $groupByColumnValueToFileNameTransformation . '".');
      }

      if ($currentFileName === null) {
        $currentFileName = $rowFileName;
      } elseif ($rowFileName !== $currentFileName) {

        echo ".";
        self::cacheLargeTableColumnValueFile(
          $projectDirectoryPath,
          $databaseName,
          $tableName,
          $rows,
          $currentFileName
        );

        $currentFileName = $rowFileName;
        $rows = [];
      } else {
        $rows[] = $row;
      }
    }

    if (count($rows)) {

      echo ".\n";
      self::cacheLargeTableColumnValueFile(
        $projectDirectoryPath,
        $databaseName,
        $tableName,
        $rows,
        $currentFileName
      );

      $rows = [];
    } else {
      //echo "\n";
    }

    echo "Read rows.\n";

    self::cacheTableMetadataFile(
      $projectDirectoryPath,
      $databaseName,
      $tableName,
      $rowsCount,
      $approximateSizeInMebibytes,
      0
    );
  }

  private static function cacheLargeTableColumnValueFile(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    array $tableRows,
    string $currentFileName
  ): array {
    //echo "Read " . count($tableRows) . " rows from the database into memory. Saving rows to file...\n";

    $backedUpDataDirectoryForLargeTable = self::getBackedUpDataDirectoryForLargeTable(
      $projectDirectoryPath,
      $databaseName,
      $tableName,
      null
    );
    $outputFileName = $backedUpDataDirectoryForLargeTable . '/' . $currentFileName . '.json';

    $serializedRows = json_encode($tableRows, JSON_PRETTY_PRINT);
    if ($serializedRows === false) {
      $serializedRows = json_last_error_msg();
    }

    file_put_contents(
      $outputFileName,
      $serializedRows
    );

    return $tableRows;
  }

  private static function saveTableDataToFilesBySections(
    string $projectDirectoryPath,
    DatabaseClient $client,
    string $databaseName,
    string $tableName,
    string $rowsQuery,
    int $rowsCount,
    float $approximateSizeInMebibytes
  ): void {
    $rowsStream = $client->getStream($rowsQuery);

    echo "Reading " . $rowsCount . " rows to files by sections...\n";

    $rows = [];
    $fileNumber = 0;
    while ($row = $rowsStream->next()) {

      if (count($rows) > 21934) {

        echo ".";

        self::cacheLargeTableSectionFile(
          $projectDirectoryPath,
          $databaseName,
          $tableName,
          $rows,
          $fileNumber,
          $fileNumber
        );
        $fileNumber++;
        $rows = [];
      } else {
        $rows[] = $row;
      }
    }

    if (count($rows)) {

      echo ".\n";
      self::cacheLargeTableSectionFile(
        $projectDirectoryPath,
        $databaseName,
        $tableName,
        $rows,
        $fileNumber,
        $fileNumber
      );
      $fileNumber++;
      $rows = [];
    } else {
      //echo "\n";
    }

    echo "Read rows.\n";

    self::cacheTableMetadataFile(
      $projectDirectoryPath,
      $databaseName,
      $tableName,
      $rowsCount,
      $approximateSizeInMebibytes,
      0
    );
  }

  /**
   * @return string[]
   */
  private static function getPrimaryKeyNames(DatabaseClient $database, array $tableProperties): array
  {
    // It's necessary to call array_values before returning the result,
    // because `array_map` (or `array_filter`?) likes to arbitrarily
    // transform lists into key-value dictionaries with weird keys that
    // will fail when the result is treated as a list.
    return array_values(
      array_map(
        function (string $tablePropertyName) use ($database): string {
          return $database->quoteColumnName($tablePropertyName);
        },
        array_filter(
          array_keys($tableProperties),
          function (string $tablePropertyName) use ($tableProperties): bool {
            return Dictionary::getOrNull($tableProperties[$tablePropertyName], 'isPrimaryKey') === true;
          }
        )
      )
    );
  }

  private static function cacheLargeTableSectionFile(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    array $tableRows,
    int $fileNumber,
    int $sectionNumber
  ): array {
    //echo "Read " . count($tableRows) . " rows from the database into memory. Saving rows to file...\n";

    $backedUpDataDirectoryForLargeTable = self::getBackedUpDataDirectoryForLargeTable(
      $projectDirectoryPath,
      $databaseName,
      $tableName,
      $sectionNumber
    );
    $outputFileName = $backedUpDataDirectoryForLargeTable . '/section_' . $fileNumber . '.json';

    $serializedRows = json_encode($tableRows, JSON_PRETTY_PRINT);
    if ($serializedRows === false) {
      $serializedRows = json_last_error_msg();
    }

    file_put_contents(
      $outputFileName,
      $serializedRows
    );

    return $tableRows;
  }

  private static function cacheTableMetadataFile(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    int $rowsCount,
    float $approximateSizeInMebibytes,
    int $sectionNumber
  ): array {
    //throw new Exception('$tableRows: ' . var_export(json_encode($tableRows), true));
    $result = [
      self::DATA_PROPERTY_NAME_dataVersion => time(),
      'rowsCount' => $rowsCount,
      'approximateSizeInMebibytes' => $approximateSizeInMebibytes,
    ];

    $outputFileName = self::getTableMetadataFilePath(
      $projectDirectoryPath,
      $databaseName,
      $tableName,
      $sectionNumber
    );

    $serializedRows = json_encode($result, JSON_PRETTY_PRINT);
    if ($serializedRows === false) {
      $serializedRows = json_last_error_msg();
    }

    file_put_contents(
      $outputFileName,
      $serializedRows
    );

    return $result;
  }

  private static function getBackedUpDataDirectoryForLargeTable(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    int|null $sectionNumber
  ): string {

    $result = self::findBackupStorageDirectory(
      $projectDirectoryPath,
      $databaseName,
      $tableName,
      $sectionNumber
    );

    if (!file_exists($result)) {
      echo "Making directory '$result'...";
      mkdir($result, recursive: true);
    }

    return realpath($result);
  }

  private static function getBackupStorageLocationConfigurationForDatabase(array $backupStorageLocations, string $databaseName): array|string
  {
    foreach ($backupStorageLocations as $pattern => $directoryConfigurationForDatabase) {
      //echo "Checking if database '$databaseName' matches pattern '$pattern'...\n";
      if (preg_match($pattern, $databaseName)) {
        return $directoryConfigurationForDatabase;
      }
    }

    throw new IncorrectConfiguration("Unable to find a matching backup location for database '$databaseName' in the backup locations configuration: " . json_encode($backupStorageLocations));
  }

  private static function getBackupCriteriaForTable(array $backupCriteria, string $databaseName, string $tableName): ?array
  {
    foreach ($backupCriteria as $databasePattern => $tablePatterns) {
      //echo "Checking if database '$databaseName' matches pattern '$pattern'...\n";
      if (preg_match($databasePattern, $databaseName)) {

        foreach ($tablePatterns as $tablePattern => $tableConfiguration) {
          if (preg_match($tablePattern, $tableName)) {
            return $tableConfiguration;
          }
        }
      }
    }

    return null;
  }

  private static function findBackupStorageDirectory(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    int|null $sectionNumber
  ): string {
    //echo "Finding backup storage directory for '$databaseName.$tableName' (section #$sectionNumber)...\n";

    $backupStorageLocations = ConfigurationFileReader::read($projectDirectoryPath, 'BackupStorageLocations.json');
    $backupStorageLocationsByTableName = self::getBackupStorageLocationConfigurationForDatabase($backupStorageLocations, $databaseName);

    if (is_array($backupStorageLocationsByTableName)) {
      foreach ($backupStorageLocationsByTableName as $pattern => $location) {
        //echo "Checking if table '$tableName' matches pattern '$pattern'...\n";

        if (preg_match($pattern, $tableName)) {

          if (is_string($location)) {
            $directoryName = $location;
            return $projectDirectoryPath . '/' . $directoryName . '/' . $databaseName . '/' . $tableName;
          } elseif (is_array($location)) {
            $directories = Dictionary::get($location, 'directories');
            if (!is_array($directories)) {
              throw new IncorrectConfiguration("Unexpected backup location `.directories` value for '$databaseName'.'$tableName' (pattern: $pattern): " . var_export($directories, return: true));
            }
            $maximumSectionsPerDirectory = Dictionary::get($location, 'maximumSectionsPerDirectory');
            if (!is_integer($maximumSectionsPerDirectory)) {
              throw new IncorrectConfiguration("Unexpected backup location `.maximumSectionsPerDirectory` value for '$databaseName'.'$tableName' (pattern: $pattern): " . var_export($maximumSectionsPerDirectory, return: true));
            }
            if ($sectionNumber === null) {
              $sectionNumber = 0;
            }
            $directoryPositionForSection = intval(floor($sectionNumber / $maximumSectionsPerDirectory));

            if (count($directories) > $directoryPositionForSection) {
              $directoryName = $directories[$directoryPositionForSection];
              return $projectDirectoryPath . '/' . $directoryName . '/' . $tableName;
            } else {
              throw new IncorrectConfiguration("Ran out of backup directories for '$databaseName'.'$tableName' (pattern: $pattern): " . var_export([
                'directories' => $directories,
                'maximumSectionsPerDirectory' => $maximumSectionsPerDirectory,
                'directoryPositionForSection' => $directoryPositionForSection,
              ], return: true));
            }
          } else {
            throw new IncorrectConfiguration("Unexpected backup location value for '$databaseName'.'$tableName' (pattern: $pattern): " . var_export($location, return: true));
          }
        }
      }
    } elseif (is_string($backupStorageLocationsByTableName)) {
      $directoryName = $backupStorageLocationsByTableName;
      return $projectDirectoryPath . '/' . $directoryName . '/' . $databaseName . '/' . $tableName;
    }

    throw new IncorrectConfiguration("Unable to find a matching backup location for '$databaseName'.'$tableName' in the backup locations configuration: " . json_encode($backupStorageLocations));
  }

  private static function getTableMetadataFilePath(
    string $projectDirectoryPath,
    string $databaseName,
    string $tableName,
    int|null $sectionNumber
  ): string {
    return self::getBackedUpDataDirectoryForLargeTable(
      $projectDirectoryPath,
      $databaseName,
      $tableName,
      $sectionNumber
    ) . '/tableMetadata.json';
  }
}
