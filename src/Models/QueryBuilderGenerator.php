<?php

namespace Pointotech\Models;

use Pointotech\Code\OutputConfigurationImplementation;
use Pointotech\Words\WordSplitter;

class QueryBuilderGenerator
{
  static function generate(string $projectDirectoryPath, string $tableName): void
  {
    $entityName = WordSplitter::splitIntoWordsAndConvertToCamelCaseAndMakeLastWordSingular(
      $projectDirectoryPath,
      $tableName
    );

    $outputDirectory = self::getOutputDirectory(
      $projectDirectoryPath,
      $entityName
    );
    $outputFileName = $outputDirectory . '/' . $entityName . 'QueryBuilder.php';

    $outputConfig = new OutputConfigurationImplementation($projectDirectoryPath);

    file_put_contents($outputFileName, '<?php

namespace ' . $outputConfig->rootNamespace() . '\\' . $entityName . ';

use Exception;

use ' . $outputConfig->rootNamespace() . '\\Database\\DatabaseClient;
use ' . $outputConfig->rootNamespace() . '\\Database\\MysqlReservedWords;
use ' . $outputConfig->rootNamespace() . '\\Database\\WhereClause;

class ' . $entityName . 'QueryBuilder
{
  /**
   * @return ?string
   */
  private function orderByColumn()
  {
    return $this->_orderByColumn;
  }
  private $_orderByColumn;

  /**
   * @return ?string
   */
  private function orderByDirection()
  {
    return $this->_orderByDirection;
  }
  private $_orderByDirection;

  /**
   * @return WhereClause[]
   */
  private function whereClauses()
  {
    return $this->_whereClauses;
  }
  private $_whereClauses;

  /**
   * @var ?int
   */
  private $_limit;

  /**
   * @param string $column
   * @param string $operation
   * @param string|int|null $value
   * @return ' . $entityName . 'QueryBuilder
   */
  function and_where($column, $operation, $value)
  {
    $this->_whereClauses[] = new WhereClause($column, $operation, $value);
    return $this;
  }

  /**
   * @return int
   */
  function count()
  {
    $query = \'select count(id) from \' . ' . $entityName . 'Model::TABLE_NAME . \' where \'
      . join(
        \' and \',
        array_map(
          /**
           * @return string
           */
          function (WhereClause $whereClause) {
            return $this->generateWhereClauseSql($whereClause);
          },
          $this->whereClauses()
        )
      );

    $rows = (new DatabaseClient())->get(
      $query,
      array_map(
        function (WhereClause $whereClause) {
          return $whereClause->value();
        },
        $this->whereClauses()
      ),
      ' . $entityName . 'Model::TABLE_NAME,
      $this->whereClauses()
    );

    return intval($rows[0][\'count(id)\']);
  }

  /**
   * @return void
   */
  function delete()
  {
    if (!count($this->whereClauses())) {
      throw new Exception(\'Can\\\'t delete without a where clause.\');
    }

    $query = \'delete from \' . ' . $entityName . 'Model::TABLE_NAME . \' where \'
      . join(
        \' and \',
        array_map(
          /**
           * @return string
           */
          function (WhereClause $whereClause) {
            return $this->generateWhereClauseSql($whereClause);
          },
          $this->whereClauses()
        )
      );

    (new DatabaseClient())->delete(
      $query,
      array_map(
        function (WhereClause $whereClause) {
          return $whereClause->value();
        },
        $this->whereClauses()
      ),
      ' . $entityName . 'Model::TABLE_NAME,
      $this->whereClauses()
    );
  }

  /**
   * @return void
   */
  function delete_all()
  {
    $query = \'delete from \' . ' . $entityName . 'Model::TABLE_NAME;

    (new DatabaseClient())->delete(
      $query,
      [],
      ' . $entityName . 'Model::TABLE_NAME,
      []
    );
  }

  /**
   * @return ?' . $entityName . 'Entity
   */
  function find()
  {
    $query = \'select * from \' . ' . $entityName . 'Model::TABLE_NAME . \' where \'
      . join(
        \' and \',
        array_map(
          /**
           * @return string
           */
          function (WhereClause $whereClause) {
            return $this->generateWhereClauseSql($whereClause);
          },
          $this->whereClauses()
        )
      );

    if ($this->orderByColumn()) {
      $query .= \' order by \' . MysqlReservedWords::quoteColumnName($this->orderByColumn());

      if ($this->orderByDirection()) {
        $query .= \' \' . $this->orderByDirection();
      }
    }

    if ($this->_limit) {
      $query .= \' limit \' . $this->_limit;
    }

    $rows = (new DatabaseClient())->get(
      $query,
      array_map(
        function (WhereClause $whereClause) {
          return $whereClause->value();
        },
        $this->whereClauses()
      ),
      ' . $entityName . 'Model::TABLE_NAME,
      $this->whereClauses()
    );

    if (count($rows) < 1) {
      return null;
    } elseif (count($rows) > 1) {
      throw new Exception(\'Query returned more than one row.\');
    }

    return ' . $entityName . 'Model::parseRow($rows[0]);
  }

  /**
   * @return ' . $entityName . 'Entity[]
   */
  function find_all()
  {
    $query = \'select * from \' . ' . $entityName . 'Model::TABLE_NAME . \' where \'
      . join(
        \' and \',
        array_map(
          /**
           * @return string
           */
          function (WhereClause $whereClause) {
            return $this->generateWhereClauseSql($whereClause);
          },
          $this->whereClauses()
        )
      );

    if ($this->orderByColumn()) {
      $query .= \' order by \' . MysqlReservedWords::quoteColumnName($this->orderByColumn());

      if ($this->orderByDirection()) {
        $query .= \' \' . $this->orderByDirection();
      }
    }

    if ($this->_limit) {
      $query .= \' limit \' . $this->_limit;
    }

    $rows = (new DatabaseClient())->get(
      $query,
      array_map(
        function (WhereClause $whereClause) {
          return $whereClause->value();
        },
        $this->whereClauses()
      ),
      ' . $entityName . 'Model::TABLE_NAME,
      $this->whereClauses()
    );

    return array_map(
      /**
       * @param array $row
       * @return ' . $entityName . 'Entity
       */
      function ($row) {
        return ' . $entityName . 'Model::parseRow($row);
      },
      $rows
    );
  }

  /**
   * @param int $limit
   * @return ' . $entityName . 'QueryBuilder
   */
  function limit($limit)
  {
    $this->_limit = $limit;
    return $this;
  }

  /**
   * @param string $column
   * @param ?string $direction
   * @return ' . $entityName . 'QueryBuilder
   */
  function order_by($column, $direction = null)
  {
    $this->_orderByColumn = $column;
    $this->_orderByDirection = $direction;
    return $this;
  }

  /**
   * @param string $column
   * @param string $operation
   * @param string|int|null $value
   * @return ' . $entityName . 'QueryBuilder
   */
  function where($column, $operation, $value)
  {
    return $this->and_where($column, $operation, $value);
  }

  /**
   * @internal
   * @param string $column
   * @param string $operation
   * @param string|int|null $value
   */
  function __construct($column, $operation, $value)
  {
    $this->_limit = null;
    $this->_orderByColumn = null;
    $this->_orderByDirection = null;
    $this->_whereClauses[] = new WhereClause($column, $operation, $value);
  }

  /**
   * @return string
   */
  private function generateWhereClauseSql(WhereClause $whereClause)
  {
    return MysqlReservedWords::quoteColumnName($whereClause->column()) . \' \' . $whereClause->operation() . \' ?\';
  }
}
');
  }

  private static function getOutputDirectory(
    string $projectDirectoryPath,
    string $namespace
  ): string {

    $result =  $projectDirectoryPath . '/output/src/' . $namespace;

    if (!file_exists($result)) {
      mkdir($result, recursive: true);
    }

    return realpath($result);
  }
}
