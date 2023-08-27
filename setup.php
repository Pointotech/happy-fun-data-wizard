<?php

echo '
===========================================
Happy Fun Data Wizard by Pointotech - Setup
===========================================
';

$autoloadFilePath = __DIR__ . "/vendor/autoload.php";

if (file_exists($autoloadFilePath)) {
  require_once $autoloadFilePath;
} else {
  echo "\n";
  die('File does not exist: "' . $autoloadFilePath . '". Run `./composerInstall.sh` to create it.' . "\n\n");
}

use Pointotech\CommandLine\CommandLineParameters;

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
  echo "\n";
  die("First parameter must be a path to the project directory. "
    . "The parameter value '$projectDirectoryPathParameter' is not a directory that exists.\n\n");
}

$dotEnvFilePath = $projectDirectoryPath . '/.env';

if (file_exists($dotEnvFilePath)) {
  echo "
Project '{$projectDirectoryPath}' is set up.

";
} else {
  echo "
Project '{$projectDirectoryPath}' is not set up. Missing:
    - The `.env` configuration file.

Setting up...
";

  $exampleDotEnvFileContent = '';
  file_put_contents($dotEnvFilePath, $exampleDotEnvFileContent);

  echo "The `.env` configuration file was created.`.

Project '{$projectDirectoryPath}' is set up.

";
}
