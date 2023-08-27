<?php

namespace Pointotech\Schemas;

use Pointotech\Code\SqlToPhpTypeConversionUtilities;
use Pointotech\Collections\Dictionary;
use Pointotech\Configuration\ConfigurationFileReader;
use Pointotech\Database\DatabaseClient;
use Pointotech\Database\MysqlVersionParser;

class CachedJsonSchemaGenerator
{
    static function generate(string $projectDirectoryPath, string $databaseName): array
    {
        //echo __CLASS__ . "::" . __METHOD__ . "\n";

        return self::getSchema($projectDirectoryPath, $databaseName);
    }

    private const SCHEMA_CACHE_DURATION_SECONDS = 24 * 60 * 60;

    private const SCHEMA_PROPERTY_NAME_schemaVersion = 'schemaVersion';

    const SCHEMA_PROPERTY_NAME_databaseServerVersion = 'databaseServerVersion';

    const SCHEMA_PROPERTY_NAME_columnsByTableName = 'columnsByTableName';

    private static function getSchema(string $projectDirectoryPath, string $databaseName): array
    {
        //echo __CLASS__ . "::" . __METHOD__ . '(' . $projectDirectoryPath . ', ' . $databaseName .  ')' . "\n";

        $schemaFileName = self::getCachedSchemaFilePath(
            $projectDirectoryPath,
            $databaseName
        );

        if (file_exists($schemaFileName)) {

            $schemaFileJson = file_get_contents($schemaFileName);
            $schema = json_decode($schemaFileJson, JSON_OBJECT_AS_ARRAY);

            if (time() < $schema[self::SCHEMA_PROPERTY_NAME_schemaVersion] + self::SCHEMA_CACHE_DURATION_SECONDS) {
                //echo "Getting cached schema...\n";
                return $schema;
            }
        }

        $databaseServerVersion = self::getDatabaseServerVersionFromServer(
            $projectDirectoryPath
        );
        $columnsByTableName = self::getColumnsByTableNameFromServer(
            $projectDirectoryPath,
            $databaseName,
            $databaseServerVersion
        );
        return self::cacheSchema(
            $projectDirectoryPath,
            $databaseName,
            $columnsByTableName,
            $databaseServerVersion
        );
    }

    private static function getColumnsByTableNameFromServer(
        string $projectDirectoryPath,
        string $databaseName,
        string $databaseServerVersion
    ): array {
        //echo __CLASS__ . "::" . __METHOD__ . "\n";

        $columnsByTableName = [];
        $db = new DatabaseClient($projectDirectoryPath, $databaseName);

        echo 'Getting table names for database "' . $databaseName . '"...' . "\n";

        foreach (self::getTableNames($db) as $tableName) {

            echo 'Creating schema output for table "' . $tableName . '"...' . "\n";

            $table = [];

            //echo 'Getting columns for table "' . $tableName . '"...' . "\n";

            foreach (self::getTableColumns($db, $databaseServerVersion, $projectDirectoryPath, $tableName) as $tableColumnName => $column) {

                //echo 'Getting information about column "' . $tableName . '"."' . $tableColumnName . '"...' . "\n";

                //echo 'Creating schema output for column "' . $tableName . '"."' . $tableColumnName . '"...' . "\n";

                $table[$tableColumnName] = $column;
            }

            $columnsByTableName[$tableName] = $table;
        }

        return $columnsByTableName;
    }

    private static function getDatabaseServerVersionFromServer(string $projectDirectoryPath): string
    {
        echo __CLASS__ . "::" . __METHOD__ . "\n";

        $db = new DatabaseClient($projectDirectoryPath);
        return $db->getVersion();
    }

    private static function cacheSchema(
        string $projectDirectoryPath,
        string $databaseName,
        array $columnsByTableName,
        string $databaseServerVersion
    ): array {
        $result = [
            self::SCHEMA_PROPERTY_NAME_schemaVersion => time(),
            self::SCHEMA_PROPERTY_NAME_databaseServerVersion => $databaseServerVersion,
            self::SCHEMA_PROPERTY_NAME_columnsByTableName => Dictionary::sortByKey($columnsByTableName),
        ];

        $outputFileName = self::getCachedSchemaFilePath(
            $projectDirectoryPath,
            $databaseName
        );

        file_put_contents(
            $outputFileName,
            json_encode($result, JSON_PRETTY_PRINT)
        );

        return $result;
    }

    private static function getCachedSchemaFilePath(
        string $projectDirectoryPath,
        string $databaseName
    ): string {

        $cacheDirectory = self::getCacheDirectory($projectDirectoryPath);
        return $cacheDirectory . '/Schema.' . $databaseName . '.json';
    }

    private static function getCacheDirectory(string $projectDirectoryPath): string
    {
        $result = $projectDirectoryPath . '/cache';

        if (!file_exists($result)) {
            mkdir($result, recursive: true);
        }

        return realpath($result);
    }

    /**
     * @return string[]
     */
    private static function getTableNames(DatabaseClient $database): array
    {
        $rows = $database->getTables($database->databaseName());

        return array_map(function ($row) {
            return $row['name'];
        }, $rows);
    }

    private static function getRelationsWithoutForeignKeys(string $projectDirectoryPath): array
    {
        $result = ConfigurationFileReader::readOrNull($projectDirectoryPath, 'RelationshipsWithoutForeignKeys.json');

        if ($result === null) {
            return [];
        } else {
            return $result;
        }
    }

    private static function getTableColumns(
        DatabaseClient $database,
        string $databaseServerVersion,
        string $projectDirectoryPath,
        string $tableName
    ): array {

        $rows = $database->get(
            '
                select * from information_schema.columns
                where ' . $database->informationSchemaColumnNames()->tableSchema() . ' = ?
                    and ' . $database->informationSchemaColumnNames()->tableName() . ' = ?
            ',
            [
                $database->databaseName(),
                $tableName,
            ]
        );

        $columns = [];

        $relationshipsWithoutForeignKeys = self::getRelationsWithoutForeignKeys($projectDirectoryPath);
        $relationshipsWithoutForeignKeysForThisTable = $relationshipsWithoutForeignKeys
            ? Dictionary::getOrNull($relationshipsWithoutForeignKeys, $tableName)
            : null;

        foreach ($rows as $row) {
            $columnName = Dictionary::get($row, $database->informationSchemaColumnNames()->columnName());
            $type = Dictionary::get($row, $database->informationSchemaColumnNames()->columnType());
            $maximumLength = Dictionary::get($row, $database->informationSchemaColumnNames()->characterMaximumLength());
            $default = Dictionary::get($row, $database->informationSchemaColumnNames()->columnDefault());
            $isPrimaryKey = $database->isColumnPrimaryKey($row);
            $isAutoIncremented = $database->isColumnAutoIncremented($row);

            $column = [
                'type' => $type,
            ];

            $relationshipForColumn = $relationshipsWithoutForeignKeysForThisTable
                ? Dictionary::getOrNull($relationshipsWithoutForeignKeysForThisTable, $columnName)
                : null;

            if ($relationshipForColumn) {
                $column['relationship'] = $relationshipForColumn;
            }

            if ($isPrimaryKey) {
                $column['isPrimaryKey'] = true;
            }

            $isNullableText = Dictionary::get(
                $row,
                $database->informationSchemaColumnNames()->isNullable()
            );

            if ($isNullableText === 'YES') {
                $column['isNullable'] = true;
            } elseif ($isNullableText === 'NO') {
                if ('timestamp' === $type && MysqlVersionParser::isLessThan5_6($databaseServerVersion)) {
                    $column['isNullable'] = false;
                    $column['isNullabilityImpliedByDatabaseServer'] = true;
                }
            }

            if ($isAutoIncremented) {
                $column['isAutoIncremented'] = true;
            }

            if ($maximumLength !== null) {
                $column['maximumLength'] = intval($maximumLength);
            }

            if ($default !== null) {
                $column['default'] = SqlToPhpTypeConversionUtilities::convertDatabaseDefaultStringToPhpDefaultValue(
                    $database,
                    $type,
                    $default
                );
            } elseif ('timestamp' === $type && MysqlVersionParser::isLessThan5_6($databaseServerVersion)) {
                $column['default'] = 'CURRENT_TIMESTAMP';
                $column['isDefaultImpliedByDatabaseServer'] = true;
            }

            //$column['raw'] = json_encode($row);

            $columns[$columnName] = $column;
        }

        return $columns;
    }
}
