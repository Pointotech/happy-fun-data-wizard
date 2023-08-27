<?php

namespace Pointotech\Database;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;

class DatabaseClientConfigurationImplementationFromConstructorGenerator
{
  static function generate(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

class DatabaseClientConfigurationImplementation implements DatabaseClientConfiguration
{
  function host()
  {
    return $this->_host;
  }
  private $_host;

  function username()
  {
    return $this->_username;
  }
  private $_username;

  function password()
  {
    return $this->_password;
  }
  private $_password;

  function name()
  {
    return $this->_name;
  }
  private $_name;

  function __construct(string $host, string $username, string $password, string $name)
  {
    $this->_host = $host;
    $this->_username = $username;
    $this->_password = $password;
    $this->_name = $name;
  }
}
';

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);
    $outputFileName = $outputDirectory . '/DatabaseClientConfigurationImplementation.php';

    file_put_contents($outputFileName, $output);
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    return OutputDirectory::get($projectDirectoryPath, 'Database');
  }
}
