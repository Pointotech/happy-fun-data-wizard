<?php

namespace Pointotech\Code;

use Dotenv\Dotenv;

use Pointotech\Configuration\IncorrectConfiguration;

class OutputConfigurationImplementation implements OutputConfiguration
{
    function rootNamespace(): string
    {
        return $this->_rootNamespace;
    }
    private $_rootNamespace;

    function __construct(string $projectDirectoryPath)
    {
        $configurationValues = $this->getConfigurationValues($projectDirectoryPath);
        $this->validateConfigurationValues($configurationValues);

        $this->_rootNamespace = $configurationValues[self::CONFIGURATION_KEY_ROOT_NAMESPACE];
    }

    private const CONFIGURATION_KEY_ROOT_NAMESPACE = 'ROOT_NAMESPACE';

    private const REQUIRED_CONFIGURATION_KEYS = [
        self::CONFIGURATION_KEY_ROOT_NAMESPACE,
    ];

    private function validateConfigurationValues(array $configuration)
    {
        foreach (self::REQUIRED_CONFIGURATION_KEYS as $configurationKey) {
            if (!array_key_exists($configurationKey, $configuration)) {
                throw new IncorrectConfiguration("One or more environment variables failed assertions: '$configurationKey' is missing.");
            }
        }
    }

    private function getConfigurationValues(string $projectDirectoryPath): array
    {
        $dotEnvFilePath = realpath($projectDirectoryPath)
            . '/' . $this->findDotEnvFileName();

        if (!file_exists($dotEnvFilePath)) {
            throw new IncorrectConfiguration('File does not exist: "' . $dotEnvFilePath . '".');
        }

        $dotEnvFileContent = file_get_contents($dotEnvFilePath);

        if ($dotEnvFileContent === false) {
            throw new IncorrectConfiguration('Unable to read "' . $dotEnvFilePath . '".');
        }

        $configuration = (array)Dotenv::parse($dotEnvFileContent);

        return $configuration;
    }

    private function findDotEnvFileName(): string
    {
        return '.env';
    }
}
