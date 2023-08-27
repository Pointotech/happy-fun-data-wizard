<?php

namespace Pointotech\Database;

class InformationSchemaColumnNamesForMysql implements InformationSchemaColumnNames
{
    function characterMaximumLength(): string
    {
        return 'CHARACTER_MAXIMUM_LENGTH';
    }

    function columnDefault(): string
    {
        return 'COLUMN_DEFAULT';
    }

    function columnKey(): string
    {
        return 'COLUMN_KEY';
    }

    function columnType(): string
    {
        return 'COLUMN_TYPE';
    }

    function columnName(): string
    {
        return 'COLUMN_NAME';
    }

    function extra(): string
    {
        return 'EXTRA';
    }

    function isNullable(): string
    {
        return 'IS_NULLABLE';
    }

    function tableName(): string
    {
        return 'TABLE_NAME';
    }

    function tableSchema(): string
    {
        return 'TABLE_SCHEMA';
    }
}
