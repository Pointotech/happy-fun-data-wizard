<?php

namespace Pointotech\Schemas;

class TableSizesByDatabaseName
{
    function tableSizesByDatabaseName(): array
    {
        return $this->_tableSizesByDatabaseName;
    }
    private $_tableSizesByDatabaseName;

    function versionTimestamp(): int
    {
        return $this->_versionTimestamp;
    }
    private $_versionTimestamp;

    function __construct(array $tableSizesByDatabaseName, int $versionTimestamp)
    {
        $this->_tableSizesByDatabaseName = $tableSizesByDatabaseName;
        $this->_versionTimestamp = $versionTimestamp;
    }
}
