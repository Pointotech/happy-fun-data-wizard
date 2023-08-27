<?php

namespace Pointotech\Database;

interface Connection
{
    /**
     * @return array[]
     */
    function get(string $query, array $parameterValues = []): array;

    function getStream(string $query, array $parameterValues = []): RowStream;
}
