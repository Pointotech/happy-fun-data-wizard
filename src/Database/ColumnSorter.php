<?php

namespace Pointotech\Database;

use Pointotech\Collections\Dictionary;
use Pointotech\Collections\List_;

class ColumnSorter
{
  static function getNamesAndSort(array $tableColumns): array
  {
    return List_::sort(
      array_keys($tableColumns),
      function (string $columnNameA, string $columnNameB) use ($tableColumns): int {

        $columnA = Dictionary::get($tableColumns, $columnNameA);
        if (Dictionary::getOrNull($columnA, 'isPrimaryKey')) {
          return -1;
        }

        $columnB = Dictionary::get($tableColumns, $columnNameB);
        if (Dictionary::getOrNull($columnB, 'isPrimaryKey')) {
          return 1;
        }

        return strcasecmp($columnNameA, $columnNameB);
      }
    );
  }
}
