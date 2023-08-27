<?php

namespace Pointotech\Code;

use Exception;

use Pointotech\Collections\Dictionary;
use Pointotech\FileSystem\Directory;
use Pointotech\Json\JsonFileReader;

class CodeGenerators
{
  static function deleteOutput(string $projectDirectoryPath)
  {
    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);

    if (file_exists($outputDirectory)) {
      self::rmdirRecursive($outputDirectory);
    }

    if (!file_exists($outputDirectory)) {
      mkdir($outputDirectory, recursive: true);
    }
  }

  static function shipOutput(string $projectDirectoryPath)
  {
    echo "shipOutput projectDirectoryPath $projectDirectoryPath\n";

    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);

    echo "shipOutput outputDirectory $outputDirectory\n";

    $shippingDirectory = self::getShippingDirectory($projectDirectoryPath);

    echo "shipOutput shippingDirectory $shippingDirectory\n";

    $sourceItemNames = Directory::listFileNames($outputDirectory);

    foreach ($sourceItemNames as $sourceItemName) {

      if ("." === $sourceItemName || ".." === $sourceItemName) {
        continue;
      }

      echo "shipOutput sourceItemName $sourceItemName\n";

      if (file_exists($outputDirectory)) {

        echo "shipOutput output directory exists\n";

        //echo "\n";
        //die();

        // TODO: Make this an option (copy only files that already exist at the destination, or copy all files).
        //self::copyFilesThatExistAtDestination("$outputDirectory/$sourceItemName", $shippingDirectory);
        shell_exec("cp -r $outputDirectory/* $shippingDirectory");
        //echo "command to run: " . "cp -r $outputDirectory/* $shippingDirectory";
      } else {
        throw new Exception("Output directory doesn't exist. Generate code before shipping it.");
      }
    }
  }

  private static function copyFilesThatExistAtDestination(string $source, string $destination)
  {
    //echo "copyFilesThatExistAtDestination source $source\n";
    //echo "copyFilesThatExistAtDestination destination $destination\n";

    $sourceItemNames = Directory::listFileNames($source);

    foreach ($sourceItemNames as $sourceItemName) {

      // TODO: Re-integrate mocking changes that were made in the DatabaseClient, so that the DatabaseClient can be automatically shipped.
      if ("." === $sourceItemName || ".." === $sourceItemName /*|| "Database" === $sourceItemName*/) {
        continue;
      }

      $sourceItemPath = "$source/$sourceItemName";
      $destinationItemPath = "$destination/$sourceItemName";

      //echo "sourceItemPath $sourceItemPath\n";
      //echo "sourceItemName $sourceItemName\n";
      //echo "destination $destinationItemPath\n";
      //echo "\n";

      if (is_dir($sourceItemPath)) {
        if (is_dir($destinationItemPath)) {
          self::copyFilesThatExistAtDestination($sourceItemPath, $destinationItemPath);
        } else {
          throw new Exception("Destination is not a directory: " . $destinationItemPath);
        }
      } elseif (is_file($sourceItemPath)) {
        if (file_exists($destinationItemPath)) {
          //throw new Exception("cp $sourceItemPath $destinationItemPath");
          //echo "cp $sourceItemPath $destinationItemPath\n";
          shell_exec("cp $sourceItemPath $destinationItemPath");
        }
      } else {
        throw new Exception("Not a file or a directory: '$sourceItemPath'.");
      }
    }

    //throw new Exception(json_encode($sourceFiles, JSON_PRETTY_PRINT));
  }

  static function getShippingDirectory(string $projectDirectoryPath): string
  {
    $outputDirectory = self::getOutputDirectory($projectDirectoryPath);

    if (file_exists($outputDirectory)) {

      $shippingLocations = JsonFileReader::read($projectDirectoryPath, 'ShippingLocations.json');

      $destinationBeforeNormalization = $projectDirectoryPath . '/' . Dictionary::get($shippingLocations, "*");
      $destination = realpath($destinationBeforeNormalization);

      if ($destination === false) {
        throw new Exception('Unable to normalize directory path: "' . $destinationBeforeNormalization . '".');
      }

      return $destination;
    } else {
      throw new Exception("Output directory doesn't exist. Generate code before shipping it.");
    }
  }

  static function escapeSingleQuotes(string $input): string
  {
    return preg_replace("/'/", "\\'", $input);
  }

  private static function getOutputDirectory(string $projectDirectoryPath): string
  {
    return $projectDirectoryPath . '/output';
  }

  private static function rmdirRecursive(string $dir)
  {
    if (is_dir($dir)) {
      $objects = scandir($dir);
      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object)) {
            self::rmdirRecursive($dir . DIRECTORY_SEPARATOR . $object);
          } else {
            unlink($dir . DIRECTORY_SEPARATOR . $object);
          }
        }
      }
      rmdir($dir);
    } else {
      throw new Exception('Is not a directory: "' . $dir . '".');
    }
  }
}
