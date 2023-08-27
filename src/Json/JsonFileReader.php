<?php

namespace Pointotech\Json;

use Exception;

class JsonFileReader
{
  static function read(string $directoryPath, string $fileName): array
  {
    return self::readAndParseContents($directoryPath, $fileName);
  }

  static function readOrEmpty(string $directoryPath, string $fileName): array
  {
    if (!file_exists($directoryPath . '/' . $fileName)) {
      return [];
    }

    return self::readAndParseContents($directoryPath, $fileName);
  }

  private static function readAndParseContents(string $directoryPath, string $fileName): array
  {
    $path = $directoryPath . '/' . $fileName;

    if (!file_exists($path)) {
      throw new Exception("File does not exist: $path");
    }

    $json = file_get_contents($path);
    return json_decode($json, JSON_OBJECT_AS_ARRAY);
  }
}
