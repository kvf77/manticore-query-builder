<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers\Pdo;

use Kvf77\Manticore\Drivers\MultiResultSetAdapterInterface;
use Kvf77\Manticore\Drivers\ResultSet;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use PDOStatement;

class MultiResultSetAdapter implements MultiResultSetAdapterInterface
{
    /**
     * @var bool
     */
    protected bool $valid = true;

    /**
     * @var PDOStatement
     */
    protected PDOStatement $statement;

    /**
     * @param PDOStatement $statement
     */
    public function __construct(PDOStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * @inheritdoc
     */
    public function getNext(): void
    {
        if (
            !$this->valid() ||
            !$this->statement->nextRowset()
        ) {
            $this->valid = false;
        }
    }

    /**
     * @inheritdoc
     */
    public function current(): ResultSetInterface
    {
        return new ResultSet(new ResultSetAdapter($this->statement));
    }

    /**
     * @inheritdoc
     */
    public function valid(): bool
    {
        return $this->valid;
    }
}
