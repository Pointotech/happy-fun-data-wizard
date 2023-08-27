<?php

namespace Pointotech\Database;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Code\OutputDirectory;

class DatabaseClientConfigurationImplementationFromDotEnvFileGenerator
{
  static function generate(string $projectDirectoryPath): void
  {
    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    $output = "<?php

namespace " . $outputConfig->rootNamespace() . '\Database;

use Dotenv\Dotenv;
use Exception;

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

  function __construct()
  {
    $configurationValues = $this->getConfigurationValues();
    $this->validateConfigurationValues($configurationValues);

    $this->_host = $configurationValues[self::CONFIGURATION_KEY_DATABASE_HOST];
    $this->_username = $configurationValues[self::CONFIGURATION_KEY_DATABASE_USERNAME];
    $this->_password = $configurationValues[self::CONFIGURATION_KEY_DATABASE_PASSWORD];
    $this->_name = $configurationValues[self::CONFIGURATION_KEY_DATABASE_NAME];
  }

  /**
   * @internal Not actually public, but the current version of PHP only supports public constants.
   */
  const CONFIGURATION_KEY_DATABASE_HOST = \'DATABASE_HOST\';

  /**
   * @internal Not actually public, but the current version of PHP only supports public constants.
   */
  const CONFIGURATION_KEY_DATABASE_USERNAME = \'DATABASE_USERNAME\';

  /**
   * @internal Not actually public, but the current version of PHP only supports public constants.
   */
  const CONFIGURATION_KEY_DATABASE_PASSWORD = \'DATABASE_PASSWORD\';

  /**
   * @internal Not actually public, but the current version of PHP only supports public constants.
   */
  const CONFIGURATION_KEY_DATABASE_NAME = \'DATABASE_NAME\';

  /**
   * @internal Not actually public, but the current version of PHP only supports public constants.
   */
  const REQUIRED_CONFIGURATION_KEYS = [
    self::CONFIGURATION_KEY_DATABASE_HOST,
    self::CONFIGURATION_KEY_DATABASE_USERNAME,
    self::CONFIGURATION_KEY_DATABASE_PASSWORD,
    self::CONFIGURATION_KEY_DATABASE_NAME,
  ];

  /**
   * @param array $configuration
   */
  private function validateConfigurationValues($configuration)
  {
    foreach (self::REQUIRED_CONFIGURATION_KEYS as $configurationKey) {
      if (!array_key_exists($configurationKey, $configuration)) {
        throw new Exception("One or more environment variables failed assertions: \'$configurationKey\' is missing.");
      }
    }
  }

  /**
   * @return array
   */
  private function getConfigurationValues()
  {
    if (!defined(\'DOCROOT\')) {
      throw new Exception(\'DOCROOT is not defined. It must be defined in order to find the environment variables file.\');
    }

    $dotEnvFilePath = DOCROOT . $this->findDotEnvFileName();

    if (!file_exists($dotEnvFilePath)) {
      throw new Exception(\'File does not exist: "\' . $dotEnvFilePath . \'".\');
    }

    $dotEnvFileContent = file_get_contents($dotEnvFilePath);

    if ($dotEnvFileContent === false) {
      throw new Exception(\'Unable to read "\' . $dotEnvFilePath . \'".\');
    }

    $configuration = (array)Dotenv::parse($dotEnvFileContent);

    return $configuration;
  }

  private function findDotEnvFileName()
  {
    if (defined(\'DOT_ENV_ENVIRONMENT_FILE_NAME\')) {
      return DOT_ENV_ENVIRONMENT_FILE_NAME;
    } else {
      throw new Exception(\'DOT_ENV_ENVIRONMENT_FILE_NAME is not defined. It must be defined in order to find the environment variables file.\');
    }
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
