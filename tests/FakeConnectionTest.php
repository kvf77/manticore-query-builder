<?php

declare(strict_types=1);

namespace Kvf77\Manticore\Tests;

use Kvf77\Manticore\Testing\FakeConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FakeConnectionTest extends TestCase
{
    public function testQueuedResultIsReturned(): void
    {
        $conn = new FakeConnection();
        $conn->queueResult([
            ['id' => 1, 'name' => 'Toyota Corolla'],
            ['id' => 2, 'name' => 'Honda Civic'],
        ]);

        $rows = $conn->query('SELECT * FROM idx')->fetchAllAssoc();

        $this->assertSame([
            ['id' => 1, 'name' => 'Toyota Corolla'],
            ['id' => 2, 'name' => 'Honda Civic'],
        ], $rows);
    }

    public function testRecordsExecutedQueriesInOrder(): void
    {
        $conn = new FakeConnection();

        $conn->query('SELECT 1');
        $conn->query('SELECT 2');

        $this->assertSame(['SELECT 1', 'SELECT 2'], $conn->executedQueries());
    }

    public function testQueueIsFifo(): void
    {
        $conn = new FakeConnection();
        $conn->queueResult([['id' => 1]])->queueResult([['id' => 2]]);

        $this->assertSame(1, $conn->query('q1')->fetchAllAssoc()[0]['id']);
        $this->assertSame(2, $conn->query('q2')->fetchAllAssoc()[0]['id']);
    }

    public function testEmptyQueueReturnsEmptyResult(): void
    {
        $conn = new FakeConnection();

        $this->assertCount(0, $conn->query('SELECT * FROM idx'));
    }

    public function testCountReflectsQueuedRows(): void
    {
        $conn = new FakeConnection();
        $conn->queueResult([['id' => 1], ['id' => 2], ['id' => 3]]);

        $this->assertCount(3, $conn->query('SELECT * FROM idx'));
    }

    public function testMultiQueryIsNotSupportedYet(): void
    {
        $this->expectException(RuntimeException::class);

        (new FakeConnection())->multiQuery(['SELECT 1']);
    }
}
