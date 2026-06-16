<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Laravel;

use Kvf77\Manticore\Drivers\ConnectionInterface;
use Kvf77\Manticore\Drivers\MultiResultSetInterface;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use Kvf77\Manticore\Helper;
use Kvf77\Manticore\SphinxQL;

/**
 * Thin Laravel-facing manager around a Manticore connection.
 * Resolve it from the container or use the Manticore facade.
 */
class Manticore
{
    public function __construct(protected ConnectionInterface $connection)
    {
    }

    /**
     * The underlying connection.
     */
    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * A fresh query builder bound to the connection.
     */
    public function query(): SphinxQL
    {
        return new SphinxQL($this->connection);
    }

    /**
     * Start a SELECT query.
     *
     * @param string ...$columns
     */
    public function select(string ...$columns): SphinxQL
    {
        return $this->query()->select(...$columns);
    }

    /**
     * Helper for one-off statements (SHOW META, CALL SNIPPETS, DESCRIBE, ...).
     */
    public function helper(): Helper
    {
        return new Helper($this->connection);
    }

    /**
     * INSERT a single row into an index.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $index, array $data): ResultSetInterface
    {
        return $this->query()->insert()->into($index)->set($data)->execute();
    }

    /**
     * REPLACE (upsert) a single row into an index.
     *
     * @param array<string, mixed> $data
     */
    public function replace(string $index, array $data): ResultSetInterface
    {
        return $this->query()->replace()->into($index)->set($data)->execute();
    }

    /**
     * Delete a document by its id.
     */
    public function delete(string $index, int $id): ResultSetInterface
    {
        return $this->query()->delete()->from($index)->where('id', '=', $id)->execute();
    }

    /**
     * Flattens a Manticore FACET multi-result set into a simple associative array.
     *
     * @return array<string, mixed>
     */
    public function facetResults(MultiResultSetInterface $result): array
    {
        $stat = [];

        while ($item = $result->getNext()) {
            foreach ($item as $value) {
                if (isset($value['count(*)'])) {
                    $stat[array_key_first($value)][current($value)] = $value['count(*)'];
                }

                if (isset($value['count_record'])) {
                    $stat['total'] = $value['count_record'];
                }
            }
        }

        return $stat;
    }
}
