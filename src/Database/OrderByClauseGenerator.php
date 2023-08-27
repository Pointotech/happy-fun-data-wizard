<?php

namespace Pointotech\Database;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;

class OrderByClauseGenerator
{
  static function generate(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

use ' . $outputConfig->rootNamespace() . '\Sql\SortOrder;

class OrderByClause
{
  function columnName(): string
  {
    return $this->_columnName;
  }
  private $_columnName;

  function sortOrder(): SortOrder
  {
    return $this->_sortOrder;
  }
  private $_sortOrder;

  function __construct(string $columnName, SortOrder $sortOrder)
  {
    $this->_columnName = $columnName;
    $this->_sortOrder = $sortOrder;
  }
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/OrderByClause.php';

    file_put_contents($outputFileName, $output);
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    return OutputDirectory::get($projectDirectoryPath, 'Database');
  }
}
