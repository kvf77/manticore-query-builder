<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Tests;

use Kvf77\Manticore\Drivers\ConnectionBase;
use Kvf77\Manticore\Drivers\MultiResultSetInterface;
use Kvf77\Manticore\Drivers\ResultSetInterface;
use RuntimeException;

/**
 * A connection test double that performs deterministic quoting/escaping without
 * touching a real Manticore/Sphinx server. It reuses the real ConnectionBase::quote()
 * logic, so compiled queries are identical to what a live driver would build.
 */
class ConnectionFake extends ConnectionBase
{
    public function connect(): bool
    {
        return true;
    }

    public function query(string $query): ResultSetInterface
    {
        throw new RuntimeException('ConnectionFake cannot execute queries; it is for compilation tests only.');
    }

    public function multiQuery(array $queue): MultiResultSetInterface
    {
        throw new RuntimeException('ConnectionFake cannot execute queries; it is for compilation tests only.');
    }

    public function escape(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'";
    }
}
