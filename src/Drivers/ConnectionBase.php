<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers;

use Kvf77\Manticore\Events\QueryExecuted;
use Kvf77\Manticore\Exception\ConnectionException;
use Kvf77\Manticore\Expression;
use mysqli;
use PDO;

abstract class ConnectionBase implements ConnectionInterface, HasQueryListeners
{
    /**
     * Registered query listeners.
     *
     * @var list<callable(QueryExecuted): void>
     */
    protected array $listeners = [];
    /**
     * The connection parameters for the database server.
     *
     * @var array
     */
    protected array $connection_params = array('host' => '127.0.0.1', 'port' => 9306, 'socket' => null);

    /**
     * Internal connection object.
     * @var mysqli|PDO|null
     */
    protected mysqli|PDO|null $connection = null;

    /**
     * Sets one or more connection parameters.
     *
     * @param array $params Associative array of parameters and values.
     */
    public function setParams(array $params): void
    {
        foreach ($params as $param => $value) {
            $this->setParam($param, $value);
        }
    }

    /**
     * Set a single connection parameter. Valid parameters include:
     *
     * * string host - The hostname, IP address, or unix socket
     * * int port - The port to the host
     * * array options - MySQLi options/values, as an associative array. Example: array(MYSQLI_OPT_CONNECT_TIMEOUT => 2)
     *
     * @param string $param Name of the parameter to modify.
     * @param mixed  $value Value to which the parameter will be set.
     */
    public function setParam(string $param, $value): void
    {
        if ($param === 'host') {
            if ($value === 'localhost') {
                $value = '127.0.0.1';
            } elseif (stripos($value, 'unix:') === 0) {
                $param = 'socket';
            }
        }
        if ($param === 'socket') {
            if (stripos($value, 'unix:') === 0) {
                $value = substr($value, 5);
            }
            $this->connection_params['host'] = null;
        }

        $this->connection_params[$param] = $value;
    }

    /**
     * Returns the connection parameters (host, port, connection timeout) for the current instance.
     *
     * @return array $params The current connection parameters
     */
    public function getParams(): array
    {
        return $this->connection_params;
    }

    /**
     * Returns the current connection established.
     *
     * @return mysqli|PDO Internal connection object
     * @throws ConnectionException If no connection has been established or open
     */
    public function getConnection(): mysqli|PDO
    {
        if (!is_null($this->connection)) {
            return $this->connection;
        }

        throw new ConnectionException('The connection to the server has not been established yet.');
    }

    /**
     * Adds quotes around values when necessary.
     * Based on FuelPHP's quoting function.
     * @inheritdoc
     */
    public function quote($value)
    {
        if ($value === null) {
            return 'null';
        } elseif ($value === true) {
            return 1;
        } elseif ($value === false) {
            return 0;
        } elseif ($value instanceof Expression) {
            // Use the raw expression
            return $value->value();
        } elseif (is_int($value)) {
            return (int) $value;
        } elseif (is_float($value)) {
            // Convert to non-locale aware float to prevent possible commas
            return sprintf('%F', $value);
        } elseif (is_array($value)) {
            // Supports MVA attributes
            return '('.implode(',', $this->quoteArr($value)).')';
        }

        return $this->escape($value);
    }

    /**
     * @inheritdoc
     */
    public function quoteArr(array $array = array()): array
    {
        $result = array();

        foreach ($array as $key => $item) {
            $result[$key] = $this->quote($item);
        }

        return $result;
    }

    /**
     * Closes and unset the connection to the Sphinx server.
     *
     * @return static
     * @throws ConnectionException
     */
    public function close(): static
    {
        $this->connection = null;

        return $this;
    }

    /**
     * Establishes a connection if needed
     * @throws ConnectionException
     */
    protected function ensureConnection(): void
    {
        try {
            $this->getConnection();
        } catch (ConnectionException $e) {
            $this->connect();
        }
    }

    /**
     * Establishes a connection to the Sphinx server.
     *
     * @return bool True if connected
     * @throws ConnectionException If a connection error was encountered
     */
    abstract public function connect(): bool;

    /**
     * @inheritdoc
     */
    public function listen(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * @inheritdoc
     */
    public function flushListeners(): void
    {
        $this->listeners = [];
    }

    /**
     * Notify registered listeners that a query has been executed.
     *
     * @param  string  $query  The compiled query string.
     * @param  float  $startedAt  The microtime(true) captured before execution.
     * @param  ResultSetInterface|null  $result  The result set (null for batches).
     */
    protected function dispatchQuery(string $query, float $startedAt, ?ResultSetInterface $result = null): void
    {
        if ($this->listeners === []) {
            return;
        }

        $event = new QueryExecuted($query, (microtime(true) - $startedAt) * 1000, $result);

        foreach ($this->listeners as $listener) {
            $listener($event);
        }
    }
}
