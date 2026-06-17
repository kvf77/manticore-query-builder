<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Events;

use Kvf77\Manticore\Drivers\ResultSetInterface;

/**
 * Emitted by a connection right after a query (or batch) has been executed.
 *
 * Register listeners on any connection that implements
 * {@see \Kvf77\Manticore\Drivers\HasQueryListeners} (the bundled drivers do),
 * or, in Laravel, via `Manticore::listen()`.
 */
final class QueryExecuted
{
    /**
     * @param  string  $query  The compiled SphinxQL string sent to the server.
     * @param  float  $time  Elapsed wall-clock time in milliseconds.
     * @param  ResultSetInterface|null  $result  The result set for a single query; null for a batched multiQuery().
     */
    public function __construct(
        public readonly string $query,
        public readonly float $time,
        public readonly ?ResultSetInterface $result = null,
    ) {
    }
}
