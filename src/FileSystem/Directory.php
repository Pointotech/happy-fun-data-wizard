<?php

namespace Pointotech\FileSystem;

class Directory
{
    static function listFileNames(string $directoryPath): array
    {
        return scandir($directoryPath);
    }
}
