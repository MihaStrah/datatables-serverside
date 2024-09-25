<?php


namespace Ozdemir\Datatables\DB;


use Ozdemir\Datatables\Column;
use Ozdemir\Datatables\Iterators\ColumnCollection;
use Ozdemir\Datatables\Query;

abstract class DBAdapter implements DatabaseInterface
{

    /**
     * @return void
     */
    abstract public function connect();

    /**
     * @param Query $query
     * @return array
     */
    abstract public function query(Query $query);

    /**
     * @param Query $query
     * @return int
     */
    abstract public function count(Query $query);

    /**
     * @param $string
     * @param Query $query
     * @return string
     */
    abstract public function escape($string, Query $query);

    /**
     * @param string $query
     * @param ColumnCollection $columns
     * @return string
     */
    public function makeQueryString(string $query, ColumnCollection $columns): string
    {
        return 'SELECT `'.implode('`, `', $columns->names())."` FROM ($query)t";
    }

    /**
     * @param Query $query
     * @param string $column
     * @return string
     */
    public function makeDistinctQueryString(Query $query, string $column, string $labelColumn = null): string
    {
      if ($labelColumn) {
        return "SELECT $column, $labelColumn FROM ($query)t GROUP BY $column";
      }
      else {
        return "SELECT $column FROM ($query)t GROUP BY $column";
      }
    }

    /**
     * @param array $filter
     * @return string
     */
    public function makeWhereString(array $filter)
    {
        return ' WHERE '.implode(' AND ', $filter);
    }

    /**
     * @param Query $query
     * @param Column $column
     * @param $word
     * @return string
     */
    public function makeLikeString(Query $query, Column $column, string $word)
    {
        return $column->name.' LIKE '.$this->escape('%'.$word.'%', $query);
    }

    /**
     * @param Query $query
     * @param Column $column
     * @param $word
     * @return string
     */
     // RCFERI
    public function makeCustomLikeString(Query $query, Column $column, string $word)
    {
        return $column->name.' LIKE '.$this->escape($word, $query);
    }

    /**
     * @param Query $query
     * @param Column $column (column should return a comma separated list of values)
     * @param $word
     * @return string
     */
     // RCFERI
    public function makeCustomFindInSetFilterString(Query $query, Column $column, string $word)
    {
        return "FIND_IN_SET({$this->escape($word, $query)}, {$column->name}) > 0";
    }

    public function makeNoFilterString()
    {
        return '1=1';
    }

    /**
     * @param array $o
     * @return string
     */
    public function makeOrderByString(array $o)
    {
        return ' ORDER BY '.implode(',', $o);
    }

    /**
     * @param $take
     * @param $skip
     * @return string
     */
    public function makeLimitString(int $take, int $skip)
    {
        return " LIMIT $take OFFSET $skip";
    }

    /**
     * @param $query
     * @return string
     */
    public function getQueryString($query)
    {
        return $query;
    }
}
