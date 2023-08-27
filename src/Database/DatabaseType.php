<?php

namespace Pointotech\Database;

use Exception;
use JsonSerializable;

class DatabaseType implements JsonSerializable
{
  static function mysql(): DatabaseType
  {
    if (self::$_mysql === null) {
      self::$_mysql = new DatabaseType('mysql');
    }
    return self::$_mysql;
  }
  private static $_mysql = null;

  static function postgresql(): DatabaseType
  {
    if (self::$_postgresql === null) {
      self::$_postgresql = new DatabaseType('postgresql');
    }
    return self::$_postgresql;
  }
  private static $_postgresql = null;

  static function parse(string $name): DatabaseType
  {
    if (self::mysql()->name() === $name) {
      return self::mysql();
    } elseif (self::postgresql()->name() === $name) {
      return self::postgresql();
    } else {
      throw new Exception('Unknown database type: ' . $name);
    }
  }

  function name(): string
  {
    return $this->_name;
  }
  private $_name;

  function jsonSerialize(): string
  {
    return $this->name();
  }

  private function __construct(string $name)
  {
    $this->_name = $name;
  }
}
