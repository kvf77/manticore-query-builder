<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers\Pdo;

use Kvf77\Manticore\Drivers\ConnectionBase;
use Kvf77\Manticore\Drivers\MultiResultSet;
use Kvf77\Manticore\Drivers\MultiResultSetInterface;
use Kvf77\Manticore\Drivers\ResultSet;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use Kvf77\Manticore\Exception\ConnectionException;
use Kvf77\Manticore\Exception\DatabaseException;
use Kvf77\Manticore\Exception\SphinxQLException;
use PDO;
use PDOException;

class Connection extends ConnectionBase
{
    /**
     * @inheritdoc
     */
    public function query(string $query): ResultSetInterface
    {
        $this->ensureConnection();

        $started = microtime(true);

        $statement = $this->connection->prepare($query);

        try {
            $statement->execute();
        } catch (PDOException $exception) {
            throw new DatabaseException('[' . $exception->getCode() . '] ' . $exception->getMessage() . ' [' . $query . ']',
                (int)$exception->getCode(), $exception);
        }

        $result = new ResultSet(new ResultSetAdapter($statement));

        $this->dispatchQuery($query, $started, $result);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function connect(): bool
    {
        $params = $this->getParams();

        $dsn = 'mysql:';
        if (isset($params['host']) && $params['host'] != '') {
            $dsn .= 'host=' . $params['host'] . ';';
        }
        if (isset($params['port'])) {
            $dsn .= 'port=' . $params['port'] . ';';
        }
        if (isset($params['charset'])) {
            $dsn .= 'charset=' . $params['charset'] . ';';
        }

        if (isset($params['socket']) && $params['socket'] != '') {
            $dsn .= 'unix_socket=' . $params['socket'] . ';';
        }

        try {
            $con = new PDO($dsn);
        } catch (PDOException $exception) {
            throw new ConnectionException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $this->connection = $con;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return true;
    }

    /**
     * @return bool
     * @throws ConnectionException
     */
    public function ping(): bool
    {
        $this->ensureConnection();

        return $this->connection !== null;
    }

    /**
     * @inheritdoc
     */
    public function multiQuery(array $queue): MultiResultSetInterface
    {
        $this->ensureConnection();

        if (count($queue) === 0) {
            throw new SphinxQLException('The Queue is empty.');
        }

        $started = microtime(true);

        try {
            $statement = $this->connection->query(implode(';', $queue));
        } catch (PDOException $exception) {
            throw new DatabaseException($exception->getMessage() .' [ '.implode(';', $queue).']', $exception->getCode(), $exception);
        }

        $result = new MultiResultSet(new MultiResultSetAdapter($statement));

        $this->dispatchQuery(implode('; ', $queue), $started);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function escape(string $value): string
    {
        $this->ensureConnection();

        return $this->connection->quote($value);
    }
}
