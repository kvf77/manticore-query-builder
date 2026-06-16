<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers;

use Kvf77\Manticore\Exception\DatabaseException;

interface MultiResultSetInterface extends \ArrayAccess, \Iterator, \Countable
{
    /**
     * Stores all the data in PHP and frees the data on the server
     *
     * @return $this
     * @throws DatabaseException
     */
    public function store(): static;

    /**
     * Returns the stored data as an array (results) of arrays (rows)
     *
     * @return ResultSetInterface[]|null
     */
    public function getStored(): ?array;

    /**
     * Returns the next result set, or false if there's no more results
     *
     * @return ResultSetInterface|false
     */
    public function getNext(): ResultSetInterface|false;

    // ArrayAccess
    public function offsetExists(mixed $offset): bool;
    public function offsetGet(mixed $offset): mixed;
    public function offsetSet(mixed $offset, mixed $value): void;
    public function offsetUnset(mixed $offset): void;

    // Iterator
    public function current(): mixed;
    public function next(): void;
    public function key(): mixed;
    public function valid(): bool;
    public function rewind(): void;

    // Countable
    public function count(): int;
}
