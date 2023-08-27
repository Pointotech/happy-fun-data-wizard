<?php

namespace Pointotech\Database;

interface RowStream
{
    function next(): array|null;

    function close(): void;
}
