<?php

namespace Ozdemir\Datatables;


use Ozdemir\Datatables\DB\DatabaseInterface;

/**
 * Class FilterHelper
 * @package Ozdemir\Datatables
 */
class FilterHelper
{
    /**
     * @var Query
     */
    private $query;
    /**
     * @var Column
     */
    private $column;
    /**
     * @var DatabaseInterface
     */
    private $db;

    /**
     * FilterHelper constructor.
     * @param Query $query
     * @param Column $column
     * @param DatabaseInterface $db
     */
    public function __construct(Query $query, Column $column, DatabaseInterface $db)
    {
        $this->query = $query;
        $this->column = $column;
        $this->db = $db;
    }

    /**
     * @param $value
     * @return string
     */
    public function escape($value): string
    {
        return $this->db->escape($value, $this->query);
    }

    /**
     * @return string
     */
    public function searchValue(): string
    {
        return $this->column->searchValue();
    }

    /**
     * @return string
     */
    public function defaultFilter(): string
    {
        return $this->db->makeLikeString($this->query, $this->column, $this->searchValue());
    }

    /**
     * @return string
     */
    // RCFERI
    public function customLikeFilter($value): string
    {
        return $this->db->makeCustomLikeString($this->query, $this->column, $value);
    }

    /**
     * @return string
     */
    // RCFERI
    public function customFindInSetFilter($value): string
    {
        return $this->db->makeCustomFindInSetFilterString($this->query, $this->column, $value);
    }

    // RCFERI
    public function noFilter(): string
    {
        return $this->db->makeNoFilterString();
    }

    /**
     * @param $low
     * @param $high
     * @return string
     */
    public function between($low, $high): string
    {
        $filter = [];
        if ($low) {
            $filter[] = $this->greaterThan($low);
        }
        if ($high) {
            $filter[] = $this->lessThan($high);
        }

        if (empty($filter)) {
            return $this->defaultFilter();
        }

        return implode(' AND ', $filter);
    }

    /**
     * Parse the column search value into a list for IN (...) filters.
     * Supports (RCFERI):
     *  - JSON arrays from yadcf filter_match_mode "json_array": [1,5,7] / ["a","b"]
     *  - Legacy yadcf pipe-separated multi-select: a|b|c
     *  - A single scalar value
     *
     * @return list<int|float|string>
     */
    public function searchValues(): array
    {
        $raw = $this->searchValue();
        if ($raw === '' || $raw === null) {
            return [];
        }

        $value = stripslashes((string) $raw);
        $trimmed = ltrim($value);
        if ($trimmed !== '' && $trimmed[0] === '[') {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $values = [];
                foreach ($decoded as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }
                    $values[] = $item;
                }
                return array_values($values);
            }
        }

        if (str_contains($value, '|')) {
            return array_values(array_filter(explode('|', $value), static function ($item) {
                return $item !== '';
            }));
        }

        return [$value];
    }

    /**
     * @param $array
     * @return string
     */
    public function whereIn($array): string
    {
        $array = array_map(function ($value) {
            return $this->escape($value);
        }, $array);

        return $this->column->name.' IN ('.implode(', ', $array).')';
    }

    /**
     * WHERE column IN (...) using searchValues() (JSON array, pipe list, or single value).
     * Returns noFilter() when the search is empty.
     *
     * @return string
     */
    // RCFERI
    public function whereInSearch(): string
    {
        $values = $this->searchValues();
        if ($values === []) {
            return $this->noFilter();
        }
        return $this->whereIn($values);
    }


    /**
     * @param $value
     * @return string
     */
    public function greaterThan($value): string
    {
        return $this->column->name.' >= '.$this->escape($value);
    }

    /**
     * @param $value
     * @return string
     */
    public function lessThan($value): string
    {
        return $this->column->name.' <= '.$this->escape($value);
    }

    /**
     * Column SQL expression for the current filter target.
     * RCFERI
     */
    public function columnName(): string
    {
        return $this->column->name;
    }

    /**
     * Escaped "(v1, v2, ...)" list for embedding in custom SQL.
     * RCFERI
     *
     * @param list<int|float|string> $values
     */
    public function escapedInList(array $values): string
    {
        $escaped = array_map(function ($value) {
            return $this->escape($value);
        }, array_values($values));

        return '('.implode(', ', $escaped).')';
    }

    /**
     * WHERE <columnExpr> IN (...) using provided values or searchValues().
     * RCFERI
     *
     * @param list<int|float|string>|null $values
     */
    public function whereInColumn(string $columnExpr, ?array $values = null): string
    {
        $values = $values ?? $this->searchValues();
        if ($values === []) {
            return $this->noFilter();
        }
        $escaped = array_map(function ($value) {
            return $this->escape($value);
        }, $values);

        return $columnExpr.' IN ('.implode(', ', $escaped).')';
    }

    /**
     * Build OR-combined SQL for each search value (JSON array / pipe / scalar).
     * $makeSql receives the already-escaped binding placeholder for one value.
     *
     * Example:
     *   return $this->anyOfSearch(fn($v) => "EXISTS (SELECT 1 FROM t WHERE t.code = {$v})");
     *
     * RCFERI
     *
     * @param callable(string): string $makeSql
     */
    public function anyOfSearch(callable $makeSql): string
    {
        $values = $this->searchValues();
        if ($values === []) {
            return $this->noFilter();
        }
        $parts = [];
        foreach ($values as $value) {
            $parts[] = '('.$makeSql($this->escape($value)).')';
        }
        if (count($parts) === 1) {
            return $parts[0];
        }
        return '('.implode(' OR ', $parts).')';
    }

    /**
     * Multi-value contains for delimited list columns (e.g. &$name&$).
     * RCFERI
     */
    public function delimitedContainsSearch(string $delimiter = '&$'): string
    {
        // Escape delimiter once per CONCAT slot. Reusing the same named
        // placeholder (:binding_N) twice causes PDO HY093.
        // Surround with % so &$a&$b&$ matches a selected token (same as legacy
        // customLikeFilter('%&$value&$%')).
        return $this->anyOfSearch(function (string $escaped) use ($delimiter) {
            $d1 = $this->escape($delimiter);
            $d2 = $this->escape($delimiter);
            return $this->column->name." LIKE CONCAT('%', ".$d1.', '.$escaped.', '.$d2.", '%')";
        });
    }

    /**
     * True when the column search is non-empty after parsing.
     * RCFERI
     */
    public function hasSearch(): bool
    {
        return $this->searchValues() !== [];
    }

    /**
     * Exact match for one value, IN (...) for many.
     * RCFERI
     */
    public function equalsSearch(): string
    {
        $values = $this->searchValues();
        if ($values === []) {
            return $this->noFilter();
        }
        if (count($values) === 1) {
            return $this->column->name.' = '.$this->escape($values[0]);
        }
        return $this->whereIn($values);
    }

}

