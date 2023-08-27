<?php

namespace Pointotech\Code\Generators;

class ConstantGeneratorForPhp5
{
  static function generate(string $name, string $value): string
  {
    return "/**
   * @internal Not actually public, but the current version of PHP only supports public constants.
   */
  const $name = $value;";
  }
}
