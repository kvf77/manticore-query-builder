<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers\Pdo;

use Kvf77\Manticore\Drivers\ResultSetAdapterInterface;
use PDO;
use PDOStatement;

class ResultSetAdapter implements ResultSetAdapterInterface
{
    /**
     * @var PDOStatement
     */
    protected PDOStatement $statement;

    /**
     * @var bool
     */
    protected bool $valid = true;

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
    public function getAffectedRows(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * @inheritdoc
     */
    public function getNumRows(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * @inheritdoc
     */
    public function getFields(): array
    {
        $fields = array();

        for ($i = 0; $i < $this->statement->columnCount(); $i++) {
            $fields[] = (object)$this->statement->getColumnMeta($i);
        }

        return $fields;
    }

    /**
     * @inheritdoc
     */
    public function isDml(): bool
    {
        return $this->statement->columnCount() == 0;
    }

    /**
     * @inheritdoc
     */
    public function store(): array
    {
        return $this->statement->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * @inheritdoc
     */
    public function toRow(int $num): void
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * @inheritdoc
     */
    public function freeResult(): void
    {
        $this->statement->closeCursor();
    }

    /**
     * @inheritdoc
     */
    public function rewind(): void
    {

    }

    /**
     * @inheritdoc
     */
    public function valid(): bool
    {
        return $this->valid;
    }

    /**
     * @inheritdoc
     */
    public function fetch(bool $assoc = true): ?array
    {
        if ($assoc) {
            $row = $this->statement->fetch(PDO::FETCH_ASSOC);
        } else {
            $row = $this->statement->fetch(PDO::FETCH_NUM);
        }

        if (!$row) {
            $this->valid = false;
            $row = null;
        }

        return $row;
    }

    /**
     * @inheritdoc
     */
    public function fetchAll(bool $assoc = true): array
    {
        if ($assoc) {
            $row = $this->statement->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $row = $this->statement->fetchAll(PDO::FETCH_NUM);
        }

        if (empty($row)) {
            $this->valid = false;
        }

        return $row;
    }
}
