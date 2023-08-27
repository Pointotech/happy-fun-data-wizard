<?php

namespace Pointotech\Database;

interface DatabaseClientConfiguration
{
    function host(): string;

    function username(): string;

    function password(): string;

    /**
     * Name of the database.
     */
    function name(): string;

    function type(): DatabaseType;
}
