<?php

echo '
=============================================
Happy Fun Data Wizard by Pointotech - Ship it
=============================================
';

$autoloadFilePath = __DIR__ . "/../vendor/autoload.php";

if (file_exists($autoloadFilePath)) {
  require_once $autoloadFilePath;
} else {
  die('File does not exist: "' . $autoloadFilePath . '". Run `composer install` to create it.' . "\n\n");
}

use Pointotech\Code\CodeGenerators;
use Pointotech\Code\OperatingSystemDependencyMissing;
use Pointotech\CommandLine\CommandLineParameters;

try {
  set_error_handler(function (int $errorLevel, string $errorMessage, string $errorFilePath, int $errorLine, ?array $errorContext = null) {
    if ($errorContext) {
      $errorMessage .= "with " . json_encode($errorContext);
    }
    throw new ErrorException(
      message: $errorMessage,
      severity: $errorLevel,
      filename: $errorFilePath,
      line: $errorLine
    );
  });

  $projectDirectoryPathParameter = CommandLineParameters::getFirst(
    parameterExplanation: 'First parameter must be a path to the project directory.'
  );

  $projectDirectoryPath = realpath($projectDirectoryPathParameter);

  if ($projectDirectoryPath === false) {
    throw new Exception(
      "First parameter must be a path to the project directory. "
        . "The parameter value '$projectDirectoryPathParameter' is not a directory that exists."
    );
  }

  CodeGenerators::shipOutput($projectDirectoryPath);
} catch (OperatingSystemDependencyMissing $error) {
  die("\n" . $error->getMessage() . "\n\n");
}

echo "\nSchema and ORM shipped successfully.\n\n";
