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
  echo "'$autoloadFilePath' not found. Running 'composerInstall.sh'...\n";
  echo "\n";

  $composerInstallPath = __DIR__ . '/composerInstall.sh';
  $composerInstallOutput = [];
  $composerInstallResultCode = null;
  exec($composerInstallPath, $composerInstallOutput, $composerInstallResultCode);

  if ($composerInstallResultCode !== 0) {
    echo "Error: '$composerInstallPath' failed.\n\n";
    die;
  } else {
    require_once $autoloadFilePath;
  }
}

use Pointotech\CommandLine\CommandLineParameters;
use Pointotech\Database\DatabaseType;

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

$filesToSetUp = [
  '.env' => 'The `.env` project configuration file',
  '.gitignore' => 'The `.gitignore` Git ignore configuration',
  'backUp.sh' => 'The `backUp.sh` interface for the database backup script',
  'backUp.php' => 'The `backUp.php` implementation of the database backup script',
  'composer.json' => 'The `composer.json` PHP dependencies configuration',
  'composerInstall.sh' => 'The `composerInstall.sh` PHP dependencies installation script',
  'README.md' => 'The `README.md` documentation file.',
];

$missingFileNames = [];

foreach ($filesToSetUp as $fileToSetUpName => $fileToSetUpDescription) {

  $filePathInProject = "$projectDirectoryPath/$fileToSetUpName";

  if (!file_exists($filePathInProject)) {
    $missingFileNames[] = $fileToSetUpName;
  }
}

if (count($missingFileNames)) {

  echo <<<TEXT
    
  Project '{$projectDirectoryPath}' is not set up. Missing:

  TEXT;

  foreach ($missingFileNames as $missingFileName) {

    $fileToSetUpDescription = $filesToSetUp[$missingFileName];

    echo " - $fileToSetUpDescription.\n";
  }

  echo <<<TEXT

  Setting up...
  
  TEXT;
}

function expandTilde(string $path): string
{
  if (str_starts_with($path, '~')) {

    $command = "echo " . $path;
    $result = shell_exec($command);

    if ($result === null) {
      throw new Exception("Failed to run command '$command'.");
    } else {
      return trim($result);
    }
  } else {

    return $path;
  }
}

function parseProjectName(string $projectDirectoryPath): string
{
  $parts = explode('/', $projectDirectoryPath);
  return $parts[count($parts) - 1];
}

/**
 * Prompt the user for input on the command line.
 */
function prompt(string $prompt): string
{
  echo $prompt;
  return trim(fgets(STDIN));
}

/**
 * Ask the user for database credentials on the command line.
 */
function promptForDatabaseCredentials(): array
{
  echo "\n";
  echo "You will now be prompted for database credentials. These database credentials will be entered in the generated `.env` project configuration file, so that the Happy Fun Data Wizard scripts can access the database.\n";
  echo "\n";

  $dbHost = prompt("Enter database host: ");
  $dbName = prompt("Enter database name: ");
  $dbUser = prompt("Enter database username: ");
  $dbPassword = prompt("Enter database password: ");
  $dbType = prompt("Enter database type (supported: "
    . DatabaseType::mysql()->name() . " and "
    . DatabaseType::postgresql()->name() . "): ");

  return [
    'DATABASE_HOST' => $dbHost,
    'DATABASE_NAME' => $dbName,
    'DATABASE_USERNAME' => $dbUser,
    'DATABASE_PASSWORD' => $dbPassword,
    'DATABASE_TYPE' => $dbType,
  ];
}

if (count($missingFileNames)) {

  foreach ($missingFileNames as $missingFileName) {

    $fileToSetUpName = $missingFileName;
    $fileToSetUpDescription = $filesToSetUp[$missingFileName];
    $filePathInProject = "$projectDirectoryPath/$fileToSetUpName";
    $projectName = parseProjectName($projectDirectoryPath);

    if (!file_exists($filePathInProject)) {

      $template = file_get_contents(__DIR__ . "/codeTemplates/$fileToSetUpName");

      $currentUserHomeDirectory = expandTilde("~");

      $currentDirectorySimplified = str_replace($currentUserHomeDirectory, "~", __DIR__);

      $template = str_replace('$happyFunDataWizardDirectory', $currentDirectorySimplified, $template);
      $template = str_replace('$projectName', $projectName, $template);

      if ($fileToSetUpName === '.env') {

        $databaseCredentials = promptForDatabaseCredentials();

        $template .= "\n";

        // Insert database credentials into the `.env` template.
        foreach ($databaseCredentials as $key => $value) {
          $template .= "$key=$value\n";
        }
      }

      $didWriteSucceed = file_put_contents($filePathInProject, $template);

      if (!$didWriteSucceed) {
        throw new Exception("Failed to write to '$filePathInProject'.");
      }

      if (str_ends_with($fileToSetUpName, '.sh')) {
        $chmodCommand = "chmod +x " . escapeshellarg($filePathInProject);
        $chmodOutput = [];
        $chmodResultCode = null;
        exec($chmodCommand, $chmodOutput, $chmodResultCode);

        if ($chmodResultCode !== 0) {
          throw new Exception("'$chmodCommand' failed.");
        }
      }

      echo <<<TEXT
      $fileToSetUpDescription was created.

      TEXT;
    }
  }

  echo "\n";
  echo "Running 'composerInstall.sh' in the project...\n";
  echo "\n";

  $composerInstallPath = "cd $projectDirectoryPath && $projectDirectoryPath/composerInstall.sh";
  $composerInstallOutput = [];
  $composerInstallResultCode = null;
  exec($composerInstallPath, $composerInstallOutput, $composerInstallResultCode);

  if ($composerInstallResultCode !== 0) {
    echo "Error: '$composerInstallPath' failed.\n\n";
    die;
  }
}

echo "
Project '{$projectDirectoryPath}' is set up.

";
