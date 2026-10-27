<?php

// RCFERI

namespace Ozdemir\Datatables\DB;

use Ozdemir\Datatables\Query;
use PDO;

/**
 * Class MSDB
 * @package Ozdemir\Datatables\DB
 */
class MSDB extends DBAdapter
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $config;

    /**
     * MSDB constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @return $this
     */
    public function connect()
    {
        $host = $this->config['host'];
        $port = isset($this->config['port']) ? $this->config['port'] : "14333";
        $user = $this->config['username'];
        $pass = $this->config['password'];
        // $database = $this->config['database'];
        // $charset = 'utf8';
        $this->pdo = new PDO("sqlsrv:Server=$host,$port", $user, $pass);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $this;
    }

    /**
     * @param Query $query
     * @return mixed
     */
    public function query(Query $query)
    {
        $sql = $this->pdo->prepare($query->pre . $query);
        $sql->execute($query->escapes);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param Query $query
     * @return mixed
     */
    public function count(Query $query)
    {
        $sql = $this->pdo->prepare($query->pre . "Select count(*) AS 'rowcount' from ($query)t");
        $sql->execute($query->escapes);
        return (int)$sql->fetchColumn();
    }

    /**
     * @param $string
     * @param Query $query
     * @return string
     */
    public function escape($string, Query $query)
    {
        $query->escapes[':binding_'.(count($query->escapes) + 1)] = $string;
        return ':binding_'.count($query->escapes);
    }



    /**
     * @param $take
     * @param $skip
     * @return string
     */
    public function makeLimitString(int $take, int $skip)
    {
        // return " LIMIT $take OFFSET $skip";
        return " OFFSET $skip ROWS FETCH NEXT $take ROWS ONLY";

    }


}
