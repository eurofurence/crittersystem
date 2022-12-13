<?php

namespace Engelsystem\Database;

use Illuminate\Database\Connection as DatabaseConnection;
use PDO;

class Database
{
    /** @var DatabaseConnection */
    protected $connection;

    public function __construct(DatabaseConnection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Run a select query
     *
     * @param array  $bindings
     * @return object[]
     */
    public function select(string $query, array $bindings = [])
    {
        return $this->connection->select($query, $bindings);
    }

    /**
     * Run a select query and return only the first result or null if no result is found.
     *
     * @param array  $bindings
     * @return object|null
     */
    public function selectOne(string $query, array $bindings = [])
    {
        return $this->connection->selectOne($query, $bindings);
    }

    /**
     * Run an insert query
     *
     * @param array  $bindings
     * @return bool
     */
    public function insert(string $query, array $bindings = [])
    {
        return $this->connection->insert($query, $bindings);
    }

    /**
     * Run an update query
     *
     * @param array  $bindings
     * @return int
     */
    public function update(string $query, array $bindings = [])
    {
        return $this->connection->update($query, $bindings);
    }

    /**
     * Run a delete query
     *
     * @param array  $bindings
     * @return int
     */
    public function delete(string $query, array $bindings = [])
    {
        return $this->connection->delete($query, $bindings);
    }

    /**
     * Get the PDO instance
     *
     * @return PDO
     */
    public function getPdo()
    {
        return $this->connection->getPdo();
    }

    /**
     * @return DatabaseConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
