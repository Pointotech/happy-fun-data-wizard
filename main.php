<?php

echo '
=========================================
Happy Fun Data Wizard by Pointotech - Run
=========================================

';

$autoloadFilePath = __DIR__ . "/vendor/autoload.php";

if (file_exists($autoloadFilePath)) {
  require_once $autoloadFilePath;
} else {
  die('File does not exist: "' . $autoloadFilePath . '". Run `composer install` to create it.' . "\n\n");
}

use Pointotech\Code\CodeGenerators;
use Pointotech\Code\OperatingSystemDependencyMissing;
use Pointotech\Code\PhpVersion;
use Pointotech\Collections\Dictionary;
use Pointotech\Collections\List_;
use Pointotech\CommandLine\CommandLineParameters;
use Pointotech\Database\DatabaseClientGenerator;
use Pointotech\Database\MigrationScriptGenerator;
use Pointotech\Entities\EntityBuilderGenerator;
use Pointotech\Entities\CodeIgniterEntityGenerator;
use Pointotech\Entities\KohanaEntityGenerator;
use Pointotech\Json\JsonFileReader;
use Pointotech\Models\CodeIgniterModelGenerator;
use Pointotech\Models\KohanaModelGenerator;
use Pointotech\Models\PropertyNamesGenerator;
use Pointotech\Models\QueryBuilderGenerator;
use Pointotech\Schemas\CachedJsonSchemaGenerator;
use Pointotech\Schemas\DatabaseTableSizesReader;
use Pointotech\Schemas\PhpSchemaGenerator;
use Pointotech\Text\Strings;
use Pointotech\Words\WordSplitter;

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

  CodeGenerators::deleteOutput($projectDirectoryPath);

  $ignoredDatabaseNames = JsonFileReader::readOrEmpty($projectDirectoryPath, 'IgnoredDatabaseNames.json');
  $ignoredTableNames = JsonFileReader::readOrEmpty($projectDirectoryPath, 'IgnoredTableNames.json');
  $runtimeEnvironmentForGeneratedCode = JsonFileReader::readOrEmpty($projectDirectoryPath, 'RuntimeEnvironmentForGeneratedCode.json');

  $databaseTableSizes = DatabaseTableSizesReader::generate($projectDirectoryPath);

  $migrationScriptParts = [];

  foreach ($databaseTableSizes->tableSizesByDatabaseName() as $databaseName => $tableSizesByTableName) {

    if (!List_::contains($ignoredDatabaseNames, $databaseName)) {
      $schema = CachedJsonSchemaGenerator::generate(
        $projectDirectoryPath,
        $databaseName
      );

      $columnsByTableName = $schema[CachedJsonSchemaGenerator::SCHEMA_PROPERTY_NAME_columnsByTableName];
      $databaseServerVersion = $schema[CachedJsonSchemaGenerator::SCHEMA_PROPERTY_NAME_databaseServerVersion];

      $phpVersionNameForGeneratedCode = Strings::toString(
        Dictionary::getOrNull($runtimeEnvironmentForGeneratedCode, 'phpVersion')
      );

      $phpVersionForGeneratedCode =
        str_starts_with($phpVersionNameForGeneratedCode, '5')
        ? PhpVersion::five()
        : PhpVersion::seven();

      PhpSchemaGenerator::generate(
        $projectDirectoryPath,
        $databaseServerVersion,
        $databaseName,
        $columnsByTableName,
        $phpVersionForGeneratedCode
      );

      $databaseConfigurationSource = Strings::cast(
        Dictionary::get($runtimeEnvironmentForGeneratedCode, 'databaseConfigurationSource')
      );

      DatabaseClientGenerator::generate($projectDirectoryPath, $databaseConfigurationSource);

      $modelType = Strings::cast(
        Dictionary::get($runtimeEnvironmentForGeneratedCode, 'modelType')
      );

      $modelsToGenerate = List_::castOrNull(Dictionary::getOrNull(
        $runtimeEnvironmentForGeneratedCode,
        'modelsToGenerate'
      ));

      foreach ($columnsByTableName as $tableName => $columns) {

        $entityName = WordSplitter::splitIntoWordsAndConvertToCamelCaseAndMakeLastWordSingular(
          $projectDirectoryPath,
          $tableName
        );

        if (
          !List_::contains($ignoredTableNames, $tableName)
          &&
          ($modelsToGenerate === null || List_::contains($modelsToGenerate, $entityName))
        ) {

          if ($modelType === 'CodeIgniter') {
            PropertyNamesGenerator::generate(
              $projectDirectoryPath,
              $databaseName,
              $tableName,
              $columns
            );
          } elseif ($modelType === 'Kohana') {
          } else {
            throw new Exception('Unknown model type: ' . $modelType);
          }

          if ($modelType === 'CodeIgniter') {
            CodeIgniterEntityGenerator::generate(
              $projectDirectoryPath,
              $databaseName,
              $tableName,
              $columns
            );
          } elseif ($modelType === 'Kohana') {
            KohanaEntityGenerator::generate(
              $projectDirectoryPath,
              $databaseName,
              $tableName,
              $columns
            );
            EntityBuilderGenerator::generate(
              $projectDirectoryPath,
              $databaseName,
              $tableName,
              $columns
            );
            QueryBuilderGenerator::generate(
              $projectDirectoryPath,
              $tableName
            );
          } else {
            throw new Exception('Unknown model type: ' . $modelType);
          }

          if ($modelType === 'CodeIgniter') {
            CodeIgniterModelGenerator::generate(
              $projectDirectoryPath,
              $databaseName,
              $tableName,
              $columns
            );
          } elseif ($modelType === 'Kohana') {
            KohanaModelGenerator::generate(
              $projectDirectoryPath,
              $databaseName,
              $tableName,
              $columns
            );
          } else {
            throw new Exception('Unknown model type: ' . $modelType);
          }

          if ($modelType === 'CodeIgniter') {
          } elseif ($modelType === 'Kohana') {
            $migrationScriptParts = MigrationScriptGenerator::generateMysql5_5To5_6KeepExistingBehaviorForTimestampColumns(
              $migrationScriptParts,
              $databaseServerVersion,
              $tableName,
              $columns
            );
          } else {
            throw new Exception('Unknown model type: ' . $modelType);
          }
        }
      }
    }
  }

  if (count($migrationScriptParts)) {
    $outputFileName = MigrationScriptGenerator::getOutputFilePath($projectDirectoryPath);

    file_put_contents($outputFileName, join("\n\n", $migrationScriptParts));
  }
} catch (OperatingSystemDependencyMissing $error) {
  die("\n" . $error->getMessage() . "\n\n");
}

echo "Schema and ORM generated successfully.\n\n";
