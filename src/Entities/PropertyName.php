<?php

namespace Pointotech\Entities;

use Pointotech\Collections\Dictionary;
use Pointotech\Words\WordSplitter;

class PropertyName
{
  static function get(
    string $projectDirectoryPath,
    array $columns,
    string $columnName
  ): string {

    $column = Dictionary::get($columns, $columnName);
    $isPrimaryKey = Dictionary::getOrNull($column, 'isPrimaryKey');

    return WordSplitter::splitIntoWordsAndConvertToCamelCaseWithoutCapitalizingFirstWord(
      $projectDirectoryPath,
      $isPrimaryKey ? 'id' : $columnName
    );
  }
}
