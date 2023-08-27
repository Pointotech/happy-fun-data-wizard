<?php

namespace Pointotech\Database;

interface InformationSchemaColumnNames
{
    function characterMaximumLength(): string;

    function columnDefault(): string;

    function columnKey(): string;

    function columnName(): string;

    function columnType(): string;

    function extra(): string;

    function isNullable(): string;

    function tableName(): string;

    function tableSchema(): string;
}
