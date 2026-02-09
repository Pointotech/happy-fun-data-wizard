<?php

namespace Pointotech\Words;

use Exception;

use Pointotech\Json\JsonFileReader;

class WordSplitter
{
  static function splitIntoWordsAndConvertToCamelCaseAndMakeLastWordSingular(
    string $projectDirectoryPath,
    string $input
  ): string {
    //echo "splitIntoWordsAndConvertToCamelCaseAndMakeLastWordSingular: " . $input . "\n";

    $words = self::makeLastWordSingular(self::splitIntoWords($projectDirectoryPath, $input));

    return join(
      '',
      array_map(
        function (string $word): string {
          return strtoupper($word[0]) . strtolower(substr($word, 1));
        },
        $words
      )
    );
  }

  static function makeLastWordSingular(array $words): array
  {
    //echo "makeLastWordSingular: " . json_encode($words) . "\n";

    $wordsCount = count($words);

    //echo "wordsCount: " . json_encode($wordsCount) . "\n";

    if ($wordsCount < 0) {
      throw new Exception('Negative word count!');
    } else if ($wordsCount < 1) {
      return [];
    } else if ($wordsCount < 2) {
      return [
        PluralWords::getSingular($words[0]),
      ];
    } else {
      $wordsExceptLast = array_slice($words, 0, -1);
      //echo "wordsExceptLast: " . json_encode($wordsExceptLast) . "\n";

      $lastWord = PluralWords::getSingular($words[$wordsCount - 1]);
      //echo "lastWord: " . json_encode($lastWord) . "\n";

      return [
        ...$wordsExceptLast,
        $lastWord,
      ];
    }
  }

  static function splitIntoWordsAndConvertToCamelCase(
    string $projectDirectoryPath,
    string $input
  ): string {

    return self::_splitIntoWordsAndConvertToCamelCase(
      $projectDirectoryPath,
      $input,
      capitalizeFirstWord: true
    );
  }

  static function splitIntoWordsAndConvertToCamelCaseWithoutCapitalizingFirstWord(
    string $projectDirectoryPath,
    string $input
  ): string {

    return self::_splitIntoWordsAndConvertToCamelCase(
      $projectDirectoryPath,
      $input,
      capitalizeFirstWord: false
    );
  }

  private static function _splitIntoWordsAndConvertToCamelCase(
    string $projectDirectoryPath,
    string $input,
    bool $capitalizeFirstWord = true
  ): string {
    $words = self::splitIntoWords($projectDirectoryPath, $input);

    return join(
      '',
      array_map(
        function (string $word) use ($capitalizeFirstWord): string {

          static $isFirstWord = true;

          $result = $isFirstWord && !$capitalizeFirstWord
            ? $word
            : WordCasing::capitalize($word);

          if ($isFirstWord) {
            $isFirstWord = false;
          }

          return $result;
        },
        $words
      )
    );
  }

  /**
   * @return string[]
   */
  static function splitIntoWords(string $projectDirectoryPath, string $input): array
  {
    $wordsToSplit = JsonFileReader::readOrEmpty($projectDirectoryPath, 'WordsToSplit.json');

    if (array_key_exists(mb_strtolower($input), $wordsToSplit)) {
      return explode(' ', $wordsToSplit[mb_strtolower($input)]);
    }

    return preg_split('/[ _]/', $input);
  }
}
