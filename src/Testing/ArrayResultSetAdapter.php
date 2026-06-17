<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Testing;

use Kvf77\Manticore\Drivers\ResultSetAdapterInterface;
use stdClass;

/**
 * A {@see ResultSetAdapterInterface} backed by a plain PHP array of associative
 * rows, with no live Manticore/Sphinx server. Lets {@see FakeConnection} return
 * deterministic, pre-canned result sets in tests.
 */
class ArrayResultSetAdapter implements ResultSetAdapterInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $rows;

    private int $position = 0;

    private int $affectedRows;

    private bool $isDml;

    /**
     * @param  array<int, array<string, mixed>>  $rows  Associative rows the result should yield.
     * @param  int  $affectedRows  Affected-row count to report for a DML result.
     * @param  bool  $isDml  Whether this result represents a write (INSERT/REPLACE/UPDATE/DELETE).
     */
    public function __construct(array $rows = [], int $affectedRows = 0, bool $isDml = false)
    {
        $this->rows = array_values($rows);
        $this->affectedRows = $affectedRows;
        $this->isDml = $isDml;
    }

    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    public function getNumRows(): int
    {
        return count($this->rows);
    }

    /**
     * @return array<int, stdClass>
     */
    public function getFields(): array
    {
        $first = $this->rows[0] ?? [];

        return array_map(
            static fn (string $name): stdClass => (object) ['name' => $name],
            array_keys($first)
        );
    }

    public function isDml(): bool
    {
        return $this->isDml;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function store(): array
    {
        return array_map('array_values', $this->rows);
    }

    public function toRow(int $num): void
    {
        $this->position = $num;
    }

    public function freeResult(): void
    {
        $this->rows = [];
        $this->position = 0;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->rows[$this->position]);
    }

    /**
     * @return array<int|string, mixed>|null
     */
    public function fetch(bool $assoc = true): ?array
    {
        if (!isset($this->rows[$this->position])) {
            return null;
        }

        $row = $this->rows[$this->position];
        $this->position++;

        return $assoc ? $row : array_values($row);
    }

    /**
     * @return array<int, array<int|string, mixed>>
     */
    public function fetchAll(bool $assoc = true): array
    {
        $this->position = count($this->rows);

        return $assoc ? $this->rows : array_map('array_values', $this->rows);
    }
}
