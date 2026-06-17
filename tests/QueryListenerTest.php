<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Tests;

use Kvf77\Manticore\Events\QueryExecuted;
use Kvf77\Manticore\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;

class QueryListenerTest extends TestCase
{
    public function testListenerReceivesExecutedQuery(): void
    {
        $conn = new FakeConnection();
        $conn->queueResult([['id' => 1]]);

        /** @var list<QueryExecuted> $events */
        $events = [];
        $conn->listen(function (QueryExecuted $event) use (&$events): void {
            $events[] = $event;
        });

        $result = $conn->query('SELECT * FROM idx');

        $this->assertCount(1, $events);
        $this->assertSame('SELECT * FROM idx', $events[0]->query);
        $this->assertGreaterThanOrEqual(0.0, $events[0]->time);
        $this->assertSame($result, $events[0]->result);
    }

    public function testMultipleListenersAllFire(): void
    {
        $conn = new FakeConnection();
        $a = 0;
        $b = 0;
        $conn->listen(function () use (&$a): void {
            $a++;
        });
        $conn->listen(function () use (&$b): void {
            $b++;
        });

        $conn->query('SELECT 1');

        $this->assertSame(1, $a);
        $this->assertSame(1, $b);
    }

    public function testFlushListenersStopsDispatch(): void
    {
        $conn = new FakeConnection();
        $calls = 0;
        $conn->listen(function () use (&$calls): void {
            $calls++;
        });
        $conn->flushListeners();

        $conn->query('SELECT 1');

        $this->assertSame(0, $calls);
    }

    public function testListenerCanReadReturnedRows(): void
    {
        $conn = new FakeConnection();
        $conn->queueResult([['id' => 123], ['id' => 456]]);

        $ids = [];
        $conn->listen(function (QueryExecuted $event) use (&$ids): void {
            if ($event->result !== null) {
                $ids = array_column($event->result->fetchAllAssoc(), 'id');
            }
        });

        $conn->query('SELECT id FROM idx');

        $this->assertSame([123, 456], $ids);
    }
}
