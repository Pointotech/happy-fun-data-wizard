<?php

namespace Pointotech\Database;

use JsonSerializable;

use Dotenv\Dotenv;

use Pointotech\Collections\Dictionary;
use Pointotech\Configuration\IncorrectConfiguration;

class DatabaseClientConfigurationImplementation implements
  DatabaseClientConfiguration,
  JsonSerializable
{
  function host(): string
  {
    return $this->_host;
  }
  private $_host;

  function username(): string
  {
    return $this->_username;
  }
  private $_username;

  function password(): string
  {
    return $this->_password;
  }
  private $_password;

  function name(): string
  {
    return $this->_name;
  }
  private $_name;

  function port(): ?int
  {
    return $this->_port;
  }
  private $_port;

  function type(): DatabaseType
  {
    return $this->_type;
  }
  private $_type;

  function jsonSerialize(): array
  {
    return [
      self::CONFIGURATION_KEY_DATABASE_HOST => $this->host(),
      self::CONFIGURATION_KEY_DATABASE_USERNAME => $this->username(),
      self::CONFIGURATION_KEY_DATABASE_PASSWORD => $this->password(),
      self::CONFIGURATION_KEY_DATABASE_NAME => $this->name(),
      self::CONFIGURATION_KEY_DATABASE_TYPE => $this->type(),
      self::CONFIGURATION_KEY_DATABASE_PORT => $this->port(),
    ];
  }

  function __construct(string $projectDirectoryPath, string | null $environmentName)
  {
    $configurationValues = $this->getConfigurationValues(
      $projectDirectoryPath,
      environmentName: $environmentName
    );
    $this->validateConfigurationValues($projectDirectoryPath, $environmentName, $configurationValues);

    $this->_host = $configurationValues[self::CONFIGURATION_KEY_DATABASE_HOST];
    $this->_username = $configurationValues[self::CONFIGURATION_KEY_DATABASE_USERNAME];
    $this->_password = $configurationValues[self::CONFIGURATION_KEY_DATABASE_PASSWORD];
    $this->_name = $configurationValues[self::CONFIGURATION_KEY_DATABASE_NAME];
    $this->_type = DatabaseType::parse($configurationValues[self::CONFIGURATION_KEY_DATABASE_TYPE]);
    $this->_port = Dictionary::getOrNull($configurationValues, self::CONFIGURATION_KEY_DATABASE_PORT);
  }

  private const CONFIGURATION_KEY_DATABASE_HOST = 'DATABASE_HOST';

  private const CONFIGURATION_KEY_DATABASE_USERNAME = 'DATABASE_USERNAME';

  private const CONFIGURATION_KEY_DATABASE_PASSWORD = 'DATABASE_PASSWORD';

  private const CONFIGURATION_KEY_DATABASE_NAME = 'DATABASE_NAME';

  private const CONFIGURATION_KEY_DATABASE_TYPE = 'DATABASE_TYPE';

  private const CONFIGURATION_KEY_DATABASE_PORT = 'DATABASE_PORT';

  private const REQUIRED_CONFIGURATION_KEYS = [
    self::CONFIGURATION_KEY_DATABASE_HOST,
    self::CONFIGURATION_KEY_DATABASE_USERNAME,
    self::CONFIGURATION_KEY_DATABASE_PASSWORD,
    self::CONFIGURATION_KEY_DATABASE_NAME,
    self::CONFIGURATION_KEY_DATABASE_TYPE,
  ];

  private function validateConfigurationValues(
    string $projectDirectoryPath,
    string | null $environmentName,
    array $configuration
  ): void {

    foreach (self::REQUIRED_CONFIGURATION_KEYS as $configurationKey) {
      if (!array_key_exists($configurationKey, $configuration)) {
        throw new IncorrectConfiguration(
          "One or more environment variables failed assertions: '$configurationKey' is missing. "
            . "Configuration file path: " . $this->getConfigurationFilePath($projectDirectoryPath, $environmentName)
        );
      }
    }
  }

  private function getConfigurationFilePath(
    string $projectDirectoryPath,
    string | null $environmentName
  ): string {

    $dotEnvFilePath = realpath($projectDirectoryPath)
      . '/' . $this->findDotEnvFileName($environmentName);

    if (!file_exists($dotEnvFilePath)) {
      throw new IncorrectConfiguration('File does not exist: "' . $dotEnvFilePath . '".');
    }

    return $dotEnvFilePath;
  }

  private function getConfigurationValues(
    string $projectDirectoryPath,
    string | null $environmentName
  ): array {

    $dotEnvFilePath = $this->getConfigurationFilePath($projectDirectoryPath, $environmentName);

    $dotEnvFileContent = file_get_contents($dotEnvFilePath);

    if ($dotEnvFileContent === false) {
      throw new IncorrectConfiguration('Unable to read "' . $dotEnvFilePath . '".');
    }

    $configuration = (array)Dotenv::parse($dotEnvFileContent);

    return $configuration;
  }

  private function findDotEnvFileName(string | null $environmentName): string
  {
    if ($environmentName === null) {
      return '.env';
    } else {
      return ".$environmentName.env";
    }
  }
}
