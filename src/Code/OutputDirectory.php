<?php

namespace Pointotech\Code;

class OutputDirectory
{
  static function get(
    string $projectDirectoryPath,
    string $namespace
  ): string {

    $result =  $projectDirectoryPath . '/output/src/' . $namespace;

    if (!file_exists($result)) {
      mkdir($result, recursive: true);
    }

    return realpath($result);
  }
}
