<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Drivers;

use Kvf77\Manticore\Events\QueryExecuted;

/**
 * A connection able to notify listeners after each executed query.
 *
 * Mirrors Laravel's `DB::listen()` for the Manticore/SphinxQL layer, which is
 * invisible to `DB::listen()` because it does not go through Laravel's database
 * connections.
 */
interface HasQueryListeners
{
    /**
     * Register a listener invoked after every executed query/batch.
     *
     * @param  callable(QueryExecuted): void  $listener
     */
    public function listen(callable $listener): void;

    /**
     * Remove all registered query listeners.
     */
    public function flushListeners(): void;
}
