<?php

namespace Pointotech\Code;

class PhpVersion
{
  static function five()
  {
    return new PhpVersion(5);
  }

  static function seven()
  {
    return new PhpVersion(7);
  }

  function majorVersionNumber(): int
  {
    return $this->_majorVersionNumber;
  }
  private $_majorVersionNumber;

  private function __construct(int $majorVersionNumber)
  {
    $this->_majorVersionNumber = $majorVersionNumber;
  }
}
