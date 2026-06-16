<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers;

use Kvf77\Manticore\Exception\ResultSetException;

interface ResultSetInterface extends \ArrayAccess, \Iterator, \Countable
{
    /**
     * Stores all the result data in the object and frees the database results
     *
     * @return $this
     */
    public function store(): static;

    /**
     * Returns the array as in version 0.9.x
     *
     * @return array|int
     * @deprecated Commodity method for simple transition to version 1.0.0
     */
    public function getStored(): array|int;

    /**
     * Checks if the specified row exists
     *
     * @param int $row The number of the row to check on
     *
     * @return bool True if the row exists, false otherwise
     */
    public function hasRow(int $row): bool;

    /**
     * Moves the cursor to the specified row
     *
     * @param int $row The row to move the cursor to
     *
     * @return $this
     * @throws ResultSetException If the row does not exist
     */
    public function toRow(int $row): static;

    /**
     * Checks if the next row exists
     *
     * @return bool True if the row exists, false otherwise
     */
    public function hasNextRow(): bool;

    /**
     * Moves the cursor to the next row
     *
     * @return $this
     * @throws ResultSetException If the next row does not exist
     */
    public function toNextRow(): static;

    /**
     * Returns the number of affected rows
     * This will be 0 for SELECT and any query not editing rows
     *
     * @return int
     */
    public function getAffectedRows(): int;

    /**
     * Fetches all the rows as an array of associative arrays
     *
     * @return array An array of associative arrays
     */
    public function fetchAllAssoc(): array;

    /**
     * Fetches all the rows as an array of indexed arrays
     *
     * @return array An array of indexed arrays
     */
    public function fetchAllNum(): array;

    /**
     * Fetches all the rows the cursor points to as an associative array
     *
     * @return array|null An associative array representing the row
     */
    public function fetchAssoc(): ?array;

    /**
     * Fetches all the rows the cursor points to as an indexed array
     *
     * @return array|null An indexed array representing the row
     */
    public function fetchNum(): ?array;

    /**
     * Frees the database from the result
     * Call it after you're done with a result set
     *
     * @return $this
     */
    public function freeResult(): static;

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
