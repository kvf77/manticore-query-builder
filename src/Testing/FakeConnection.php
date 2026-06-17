<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Testing;

use Kvf77\Manticore\Drivers\ConnectionBase;
use Kvf77\Manticore\Drivers\MultiResultSetInterface;
use Kvf77\Manticore\Drivers\ResultSet;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use RuntimeException;

/**
 * A fully-featured connection test double: it never touches a Manticore/Sphinx
 * server, returns pre-canned result sets, and records every executed query.
 *
 * Typical use: stub the search layer in application tests so an endpoint backed
 * by Manticore returns known document ids, without building a live index.
 *
 *     $fake = new FakeConnection();
 *     $fake->queueResult([['id' => 123], ['id' => 456]]);
 *     // bind $fake as the ConnectionInterface, hit the endpoint...
 *     $fake->executedQueries(); // assert what was sent
 *
 * It reuses {@see ConnectionBase} quoting, so compiled queries are identical to
 * what a live driver would build. multiQuery()/facets are not supported yet.
 */
class FakeConnection extends ConnectionBase
{
    /**
     * @var list<ResultSetInterface>
     */
    private array $queue = [];

    /**
     * @var list<string>
     */
    private array $executed = [];

    public function connect(): bool
    {
        return true;
    }

    /**
     * Queue the rows the next executed query should return (FIFO).
     *
     * @param  array<int, array<string, mixed>>  $rows  Associative rows to yield.
     * @param  int  $affectedRows  Affected-row count to report for a DML result.
     * @param  bool  $isDml  Whether this result represents a write.
     */
    public function queueResult(array $rows = [], int $affectedRows = 0, bool $isDml = false): static
    {
        $this->queue[] = new ResultSet(new ArrayResultSetAdapter($rows, $affectedRows, $isDml));

        return $this;
    }

    /**
     * All query strings executed against this connection, in order.
     *
     * @return list<string>
     */
    public function executedQueries(): array
    {
        return $this->executed;
    }

    public function query(string $query): ResultSetInterface
    {
        $started = microtime(true);
        $this->executed[] = $query;

        $result = array_shift($this->queue) ?? new ResultSet(new ArrayResultSetAdapter());

        $this->dispatchQuery($query, $started, $result);

        return $result;
    }

    /**
     * @param  array<int, string>  $queue
     */
    public function multiQuery(array $queue): MultiResultSetInterface
    {
        throw new RuntimeException(
            'FakeConnection does not support multiQuery()/facets yet; queue single query() results instead.'
        );
    }

    public function escape(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
