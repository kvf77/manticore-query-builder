# Query listener

Manticore/SphinxQL queries do **not** go through Laravel's database connections,
so Laravel's `DB::listen()` never sees them. This package exposes its own
listener hook — the Manticore-layer counterpart to `DB::listen()` — so you can
log, time, profile or capture every query the builder sends.

Any bundled connection (the MySQLi and PDO drivers, and the
[`FakeConnection`](12-testing.md)) implements
[`Kvf77\Manticore\Drivers\HasQueryListeners`](../src/Drivers/HasQueryListeners.php).

## The event

Each listener receives an immutable
[`Kvf77\Manticore\Events\QueryExecuted`](../src/Events/QueryExecuted.php):

| Property  | Type                       | Meaning                                                        |
|-----------|----------------------------|---------------------------------------------------------------|
| `$query`  | `string`                   | The compiled SphinxQL string that was sent to the server.     |
| `$time`   | `float`                    | Elapsed wall-clock time, in **milliseconds**.                 |
| `$result` | `?ResultSetInterface`      | The result for a single `query()`; `null` for a batched `multiQuery()`. |

## Native usage

```php
use Kvf77\Manticore\Events\QueryExecuted;

$conn->listen(function (QueryExecuted $q): void {
    error_log(sprintf('[manticore %.2f ms] %s', $q->time, $q->query));
});

$qb = new SphinxQL($conn);
$qb->select()->from('books')->where('author', 'Pratchett')->execute();
// → [manticore 0.42 ms] SELECT * FROM books WHERE author = 'Pratchett'
```

Remove all listeners with `flushListeners()`:

```php
$conn->flushListeners();
```

## Laravel usage

Use the `Manticore` facade (or inject the `Manticore` manager). Register the
listener once, e.g. in a service provider's `boot()`:

```php
use Illuminate\Support\Facades\Log;
use Kvf77\Manticore\Events\QueryExecuted;
use Kvf77\Manticore\Laravel\Facades\Manticore;

public function boot(): void
{
    if (config('app.debug')) {
        Manticore::listen(function (QueryExecuted $q): void {
            Log::channel('manticore')->debug($q->query, ['ms' => $q->time]);
        });
    }
}
```

`Manticore::listen()` is a no-op if the underlying connection does not support
listeners, so it is always safe to call.

## Reading result rows in a listener

`$q->result` is the live result set. Iterating or fetching from it advances its
cursor, which would affect the code that ran the query. If you need to inspect
rows inside a listener (e.g. to capture the matched document ids), **buffer the
result first** with `store()` so it can be read again afterwards:

```php
Manticore::listen(function (QueryExecuted $q): void {
    if ($q->result !== null) {
        $ids = array_column($q->result->store()->fetchAllAssoc(), 'id');
        // record "$q->query returned $ids" for fixtures, profiling, etc.
    }
});
```

> For `multiQuery()` / facet batches, `$q->result` is `null` — the listener
> still fires with the combined query string and timing.
