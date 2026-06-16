<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers\Mysqli;

use Kvf77\Manticore\Drivers\MultiResultSetAdapterInterface;
use Kvf77\Manticore\Drivers\ResultSet;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use Kvf77\Manticore\Exception\ConnectionException;

class MultiResultSetAdapter implements MultiResultSetAdapterInterface
{
    /**
     * @var bool
     */
    protected bool $valid = true;

    /**
     * @var Connection
     */
    protected Connection $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @inheritdoc
     * @throws ConnectionException
     */
    public function getNext(): void
    {
        if (
            !$this->valid() ||
            !$this->connection->getConnection()->more_results()
        ) {
            $this->valid = false;
        } else {
            $this->connection->getConnection()->next_result();
        }
    }

    /**
     * @inheritdoc
     * @throws ConnectionException
     */
    public function current(): ResultSetInterface
    {
        $adapter = new ResultSetAdapter($this->connection, $this->connection->getConnection()->store_result());
        return new ResultSet($adapter);
    }

    /**
     * @inheritdoc
     * @throws ConnectionException
     */
    public function valid(): bool
    {
        return $this->connection->getConnection()->errno == 0 && $this->valid;
    }
}
