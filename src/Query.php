<?php

namespace Ozdemir\Datatables;

/**
 * Class Query
 * @package Ozdemir\Datatables
 */
class Query
{
    /**
     * Bare query string, user input
     * @var
     */
    public $escapes = [];

    /**
     * Query string
     * @var
     */
    public $sql;

    /**
     * Builder constructor.
     *
     * @param $query
     */

     // RCFERI
    public $pre = '';

    public function __construct($query = '')
    {
        $this->sql = $query;
    }

    /**
     * Builder constructor.
     *
     * @param $query
     */
    public function set($query): void
    {
        $this->sql = $query;
    }
    
    // RCFERI
    public function setPre($preQuery): void
    {
        $this->pre = $preQuery;
    }

    /**
     *
     */
    public function __toString()
    {
        return $this->sql;
    }

}