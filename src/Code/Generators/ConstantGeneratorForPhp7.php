<?php

namespace Pointotech\Code\Generators;

class ConstantGeneratorForPhp7
{
  static function generate(string $name, string $value): string
  {
    return "private const $name = $value;";
  }
}
