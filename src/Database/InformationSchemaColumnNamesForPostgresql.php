<?php

namespace Pointotech\Database;

class InformationSchemaColumnNamesForPostgresql implements InformationSchemaColumnNames
{
    function characterMaximumLength(): string
    {
        return 'character_maximum_length';
    }

    function columnDefault(): string
    {
        return 'column_default';
    }

    function columnKey(): string
    {
        return 'column_key';
    }

    function columnType(): string
    {
        return 'data_type';
    }

    function columnName(): string
    {
        return 'column_name';
    }

    function extra(): string
    {
        return 'extra';
    }

    function isNullable(): string
    {
        return 'is_nullable';
    }

    function tableName(): string
    {
        return 'table_name';
    }

    function tableSchema(): string
    {
        return 'table_schema';
    }
}
