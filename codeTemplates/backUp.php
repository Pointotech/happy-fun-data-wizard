<?php

echo '
==================
Back up databases:
==================

';

$autoloadFilePath = __DIR__ . "/vendor/autoload.php";

if (file_exists($autoloadFilePath)) {
    require_once $autoloadFilePath;
} else {
    die('File does not exist: "' . $autoloadFilePath . '". Run `composer install` to create it.' . "\n\n");
}

use Dotenv\Dotenv;

function getConfigurationValues(string $projectDirectoryPath): array
{
    $dotEnvFilePath = realpath($projectDirectoryPath)
        . '/' . findDotEnvFileName();

    if (!file_exists($dotEnvFilePath)) {
        throw new Exception('File does not exist: "' . $dotEnvFilePath . '".');
    }

    $dotEnvFileContent = file_get_contents($dotEnvFilePath);

    if ($dotEnvFileContent === false) {
        throw new Exception('Unable to read "' . $dotEnvFilePath . '".');
    }

    $configuration = (array)Dotenv::parse($dotEnvFileContent);

    return $configuration;
}

function findDotEnvFileName(): string
{
    return '.env';
}

$configuration = getConfigurationValues(__DIR__);

if (!array_key_exists('GENERATE_SCHEMA_DIRECTORY', $configuration)) {
    throw new Exception("The configuration file is missing an entry for 'GENERATE_SCHEMA_DIRECTORY'.");
}

$GENERATE_SCHEMA_DIRECTORY = $configuration['GENERATE_SCHEMA_DIRECTORY'];

//echo 'GENERATE_SCHEMA_DIRECTORY: ' . $GENERATE_SCHEMA_DIRECTORY . "\n\n";

$command = "$GENERATE_SCHEMA_DIRECTORY/backUp.sh " . __DIR__;
echo "Running command: $command" . "\n";

passthru($command);
