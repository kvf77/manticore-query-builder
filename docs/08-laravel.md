# Laravel integration

The package ships an optional Laravel bridge: a service provider (auto-discovered),
a config file, a `Manticore` manager and a `Manticore` facade.

## Setup

The service provider is registered automatically via package discovery. Publish the
config if you want to edit it:

```bash
php artisan vendor:publish --tag=manticore-config
```

This writes `config/manticore.php`:

```php
return [
    'driver' => env('MANTICORE_DRIVER', 'pdo'), // 'pdo' or 'mysqli'
    'host'   => env('MANTICORE_HOST', '127.0.0.1'),
    'port'   => (int) env('MANTICORE_PORT', 9306),
    'socket' => env('MANTICORE_SOCKET'),
];
```

Set your connection in `.env`:

```dotenv
MANTICORE_HOST=127.0.0.1
MANTICORE_PORT=9306
# MANTICORE_DRIVER=pdo
```

The connection is registered as a **singleton** bound to
`Kvf77\Manticore\Drivers\ConnectionInterface`.

## The facade

```php
use Kvf77\Manticore\Laravel\Facades\Manticore;

// Builder
$rows = Manticore::select('id', 'title')
    ->from('articles')
    ->match('title', 'hello')
    ->where('published', 1)
    ->execute();

// Convenience writes
Manticore::insert('articles', ['id' => 1, 'title' => 'Hello']);
Manticore::replace('articles', ['id' => 1, 'title' => 'Hello again']);
Manticore::delete('articles', 1);

// A raw builder when you need full control
$qb = Manticore::query();

// Helper statements
$meta = Manticore::helper()->showMeta()->execute();

// Flatten a faceted MultiResultSet (see Facets doc)
$stats = Manticore::facetResults($qb->executeBatch());
```

## The manager (dependency injection)

Prefer injection over the facade? Type-hint the manager or the connection:

```php
use Kvf77\Manticore\Laravel\Manticore;
use Kvf77\Manticore\Drivers\ConnectionInterface;

class ArticleSearch
{
    public function __construct(private Manticore $manticore) {}

    public function find(string $term): array
    {
        return $this->manticore->select('id', 'title')
            ->from('articles')
            ->match('title', $term)
            ->execute()
            ->fetchAllAssoc();
    }
}
```

Or resolve manually:

```php
$manticore = app(\Kvf77\Manticore\Laravel\Manticore::class);
$conn      = app(\Kvf77\Manticore\Drivers\ConnectionInterface::class);
```

## Manager API

| Method | Description |
|--------|-------------|
| `query(): SphinxQL` | A fresh builder on the configured connection |
| `select(...$columns): SphinxQL` | Start a SELECT |
| `insert(string $index, array $data): ResultSetInterface` | INSERT one row |
| `replace(string $index, array $data): ResultSetInterface` | REPLACE (upsert) one row |
| `delete(string $index, int $id): ResultSetInterface` | DELETE by id |
| `helper(): Helper` | Helper for SHOW/CALL/DESCRIBE statements |
| `facetResults(MultiResultSetInterface $r): array` | Flatten a faceted multi-result-set |
| `connection(): ConnectionInterface` | The underlying connection |
