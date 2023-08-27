<?php

namespace Pointotech\Schemas;

use Pointotech\Collections\Dictionary;
use Pointotech\Database\DatabaseClient;

class DatabaseTableSizesReader
{
    static function generate(string $projectDirectoryPath): TableSizesByDatabaseName
    {
        $schemaFileName = self::getOutputFilePath($projectDirectoryPath);

        if (file_exists($schemaFileName)) {

            $schemaFileJson = file_get_contents($schemaFileName);
            $schema = json_decode($schemaFileJson, JSON_OBJECT_AS_ARRAY);

            if (time() < $schema[self::PROPERTY_NAME_tablesVersion] + self::CACHE_DURATION_SECONDS) {
                //echo "Getting cached tables...\n";
                return new TableSizesByDatabaseName(
                    Dictionary::get($schema, self::PROPERTY_NAME_tables),
                    Dictionary::get($schema, self::PROPERTY_NAME_tablesVersion)
                );
            }
        }

        $tableListsByDatabaseName = self::getTableListsByDatabaseName($projectDirectoryPath);
        return self::cache($projectDirectoryPath, $tableListsByDatabaseName);
    }

    private const CACHE_DURATION_SECONDS = 24 * 60 * 60;

    private const PROPERTY_NAME_tables = 'tables';

    private const PROPERTY_NAME_tablesVersion = 'tablesVersion';

    private static function getOutputFilePath(string $projectDirectoryPath): string
    {
        $cacheDirectory = self::getCacheDirectory($projectDirectoryPath);
        return $cacheDirectory . '/Tables.json';
    }

    private static function getCacheDirectory(string $projectDirectoryPath): string
    {
        $result = $projectDirectoryPath . '/cache';

        if (!file_exists($result)) {
            mkdir($result, recursive: true);
        }

        return realpath($result);
    }

    private static function getTableListsByDatabaseName(string $projectDirectoryPath): array
    {
        //echo __CLASS__ . "::" . __METHOD__ . "\n";

        $result = [];
        $db = new DatabaseClient($projectDirectoryPath);

        echo 'Getting database names...' . "\n";

        foreach (self::getDatabaseNames($db) as $databaseName) {

            echo 'Creating tables output for database "' . $databaseName . '"...' . "\n";

            $tables = [];

            echo 'Getting tables for database "' . $databaseName . '"...' . "\n";

            foreach ($db->getTables($databaseName) as $table) {

                echo 'Listing table "' . $databaseName . '"."' . $table['name'] . '"...' . "\n";

                $tables[] = $table;
            }

            $result[$databaseName] = $tables;
        }

        return Dictionary::sortByKey($result);
    }

    /**
     * @return string[]
     */
    private static function getDatabaseNames(DatabaseClient $database): array
    {
        $rows = $database->get(
            'select distinct '
                . $database->informationSchemaColumnNames()->tableSchema() . ' '
                . 'from information_schema.tables'
        );

        $result = array_map(function ($row) use ($database) {
            return Dictionary::get(
                $row,
                $database->informationSchemaColumnNames()->tableSchema()
            );
        }, $rows);

        return Dictionary::sortByKey($result);
    }

    private static function cache(string $projectDirectoryPath, array $tableListsByDatabaseName): TableSizesByDatabaseName
    {
        $versionTimestamp = time();
        $result = [
            self::PROPERTY_NAME_tablesVersion => $versionTimestamp,
            self::PROPERTY_NAME_tables => $tableListsByDatabaseName,
        ];

        $outputFileName = self::getOutputFilePath($projectDirectoryPath);

        file_put_contents(
            $outputFileName,
            json_encode($result, JSON_PRETTY_PRINT)
        );

        return new TableSizesByDatabaseName($tableListsByDatabaseName, $versionTimestamp);
    }
}
