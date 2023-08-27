<?php

namespace Pointotech\Configuration;

use Exception;

class ConfigurationFileReader
{
    static function read(string $projectDirectoryPath, string $configurationFileName): array
    {
        $result = self::readOrNull($projectDirectoryPath, $configurationFileName);

        if ($result === null) {
            $configurationFilePath = $projectDirectoryPath . '/' . $configurationFileName;
            throw new IncorrectConfiguration('Configuration file "' . $configurationFilePath . '" does not exist.');
        }

        return $result;
    }

    static function readOrNull(string $projectDirectoryPath, string $configurationFileName): ?array
    {
        $configurationFilePath = $projectDirectoryPath . '/' . $configurationFileName;

        if (file_exists($configurationFilePath)) {
            $configurationFileText = file_get_contents($configurationFilePath);

            if ($configurationFileText === false) {
                throw new IncorrectConfiguration('Unable to read configuration file "' . $configurationFileName . '". It should be at "' . $configurationFilePath . '".');
            }

            $configuration = json_decode($configurationFileText, associative: true);

            if ($configuration === null) {
                throw new IncorrectConfiguration('Unable to parse the contents of configuration file "' . $configurationFileName . '". Configuration file text: ' . json_encode($configurationFileText));
            }

            if (is_array($configuration)) {
                return $configuration;
            } else {
                throw new IncorrectConfiguration('Configuration file "' . $configurationFileName . '" does not contain a JSON-encoded array. Configuration: ' . json_encode($configuration));
            }
        } else {
            return null;
        }
    }
}
