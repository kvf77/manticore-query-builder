# Testing

`Kvf77\Manticore\Testing\FakeConnection` is a connection test double that never
touches a live Manticore/Sphinx server. It:

- returns **pre-canned result sets** you queue up front,
- **records every executed query** so you can assert what was sent,
- reuses the real `ConnectionBase` quoting/escaping, so the compiled queries are
  identical to what a live driver would build.

This lets you test code paths backed by Manticore — including search endpoints —
**without building a live index** in the test environment.

```php
use Kvf77\Manticore\SphinxQL;
use Kvf77\Manticore\Testing\FakeConnection;

$conn = new FakeConnection();
$conn->queueResult([
    ['id' => 1, 'name' => 'Toyota Corolla'],
    ['id' => 2, 'name' => 'Honda Civic'],
]);

$rows = (new SphinxQL($conn))->select()->from('cars')->execute()->fetchAllAssoc();
// [['id' => 1, 'name' => 'Toyota Corolla'], ['id' => 2, 'name' => 'Honda Civic']]

$conn->executedQueries(); // ['SELECT * FROM cars']
```

## API

| Method                                        | Purpose                                                          |
|-----------------------------------------------|------------------------------------------------------------------|
| `queueResult(array $rows, int $affectedRows = 0, bool $isDml = false): static` | Queue the rows the next executed query returns (FIFO). Chainable. |
| `executedQueries(): list<string>`             | All executed query strings, in order.                            |

Calls beyond the queued results return an **empty** result set. Because
`FakeConnection` supports [query listeners](11-query-listener.md), you can also
assert on queries via `listen()`.

## Stubbing search in a Laravel application test

The real win: make a Manticore-backed endpoint return **known document ids**
without a live index. Bind the fake as the connection before exercising the code
under test, so the manager/builder resolve it:

```php
use Kvf77\Manticore\Drivers\ConnectionInterface;
use Kvf77\Manticore\Laravel\Manticore;
use Kvf77\Manticore\Testing\FakeConnection;

public function test_search_endpoint_returns_matched_cars(): void
{
    $fake = new FakeConnection();
    // the geo/full-text search returns these ids; hydrate the rest from MySQL fixtures
    $fake->queueResult([['id' => 123], ['id' => 456]]);

    $this->app->instance(ConnectionInterface::class, $fake);
    $this->app->forgetInstance(Manticore::class); // rebuild the manager around the fake

    $response = $this->getJson('/api/cars/search?q=corolla');

    $response->assertOk()->assertJsonPath('data.0.id', 123);
    $this->assertStringContainsString("MATCH('corolla')", $fake->executedQueries()[0]);
}
```

The matched ids you queue can come from a real capture: register a
[query listener](11-query-listener.md) against a dev server once, record
`query → returned ids`, then replay those ids here — so the fixture mirrors real
data without a live index in CI.

## Limitations

- `multiQuery()` (and therefore FACET batches) is **not supported yet** — it
  throws. Queue single `query()` results instead. Support may come in a later
  release.
