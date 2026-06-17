<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Kvf77\Manticore\Drivers\ConnectionInterface;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use Kvf77\Manticore\Helper;
use Kvf77\Manticore\SphinxQL;

/**
 * @method static ConnectionInterface connection()
 * @method static void listen(callable $listener)
 * @method static SphinxQL query()
 * @method static SphinxQL select(string ...$columns)
 * @method static Helper helper()
 * @method static ResultSetInterface insert(string $index, array $data)
 * @method static ResultSetInterface replace(string $index, array $data)
 * @method static ResultSetInterface delete(string $index, int $id)
 * @method static array facetResults(\Kvf77\Manticore\Drivers\MultiResultSetInterface $result)
 *
 * @see \Kvf77\Manticore\Laravel\Manticore
 */
class Manticore extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'manticore';
    }
}
