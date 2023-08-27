<?php

namespace Pointotech\Database;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;

class WhereClauseGenerator
{
  static function generate(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

class WhereClause
{
  /**
   * @return string
   */
  function column()
  {
    return $this->_column;
  }
  private $_column;

  /**
   * @return string
   */
  function operation()
  {
    return $this->_operation;
  }
  private $_operation;

  /**
   * @return string|int|null
   */
  function value()
  {
    return $this->_value;
  }
  private $_value;

  /**
   * @param string $column
   * @param string $operation
   * @param string|int|null $value
   */
  function __construct($column, $operation, $value)
  {
    $this->_column = $column;
    $this->_operation = $operation;
    $this->_value = $value;
  }
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/WhereClause.php';

    file_put_contents($outputFileName, $output);
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    return OutputDirectory::get($projectDirectoryPath, 'Database');
  }
}
