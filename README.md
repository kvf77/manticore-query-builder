# Manticore Query Builder

A modern PHP query builder for [Manticore Search](https://manticoresearch.com/)
(and Sphinx) speaking the **SphinxQL** dialect over the MySQL protocol.

This is a maintained, modernised fork of
[`foolz/sphinxql-query-builder`](https://github.com/FoolCode/SphinxQL-Query-Builder),
rebuilt for PHP 8.3+ with a cleaner `WHERE` compiler, `OR`/nested groups,
`REGEX()` support and first-class Laravel integration.

- Requires **PHP 8.3+**
- Drivers: **PDO** (default) or **MySQLi**
- No framework dependency in the core; optional Laravel bridge included
- Licensed under **Apache-2.0** (see `LICENSE` and `NOTICE`)

## Documentation

Full docs live in [`docs/`](docs/):

- [Getting started](docs/01-getting-started.md)
- [SELECT & WHERE](docs/02-select-where.md)
- [Full-text MATCH](docs/03-fulltext-match.md)
- [**Facets**](docs/04-facets.md) — single/multiple/computed facets, `executeBatch()`, the legend/stats pattern
- [Geo search](docs/10-geo-search.md) — distance & polygon search
- [Writing data](docs/05-write-queries.md)
- [Reading results](docs/06-results.md)
- [Helpers & Percolate](docs/07-helpers-percolate.md)
- [Laravel integration](docs/08-laravel.md)
- [Migrating from foolz/sphinxql-query-builder](docs/09-migrating-from-foolz.md)

## Installation

```bash
composer require kvf77/manticore-query-builder
```

## Quick start (plain PHP)

```php
use Kvf77\Manticore\Drivers\Pdo\Connection;
use Kvf77\Manticore\SphinxQL;

$conn = new Connection();
$conn->setParams(['host' => '127.0.0.1', 'port' => 9306]);

$result = (new SphinxQL($conn))
    ->select('id', 'title')
    ->from('articles')
    ->match('title', 'manticore')
    ->where('published', 1)
    ->orderBy('id', 'desc')
    ->limit(20)
    ->execute();

foreach ($result as $row) {
    // ...
}
```

## WHERE: AND, OR and nested groups

```php
$qb->select()->from('idx')
    ->where('a', 1)
    ->orWhere('b', 2)
    ->where(function (SphinxQL $q) {
        $q->where('c', 3)->orWhere('d', 4);
    });
// WHERE a = 1 OR b = 2 AND (c = 3 OR d = 4)
```

Operators: `=` (default), comparison operators, `IN`, `NOT IN`, `BETWEEN`.

```php
$qb->where('id', 'IN', [1, 2, 3]);          // id IN (1, 2, 3)
$qb->where('price', 'BETWEEN', [10, 100]);  // price BETWEEN 10 AND 100
```

## REGEX (Manticore)

```php
$qb->select()->from('idx')->regex('title', '.*foo.*');
// WHERE REGEX(title, '.*foo.*')
```

## Insert / Replace / Update / Delete

```php
(new SphinxQL($conn))->insert()->into('idx')->set(['id' => 1, 'name' => 'John'])->execute();
(new SphinxQL($conn))->replace()->into('idx')->set(['id' => 1, 'name' => 'John'])->execute();
(new SphinxQL($conn))->update('idx')->value('views', 5)->where('id', 1)->execute();
(new SphinxQL($conn))->delete()->from('idx')->where('id', 1)->execute();
```

## Laravel integration

The service provider is auto-discovered. Publish the config if you want to tweak it:

```bash
php artisan vendor:publish --tag=manticore-config
```

Set your connection in `.env`:

```dotenv
MANTICORE_HOST=127.0.0.1
MANTICORE_PORT=9306
# MANTICORE_DRIVER=pdo   # or mysqli
```

Use the facade:

```php
use Kvf77\Manticore\Laravel\Facades\Manticore;

Manticore::insert('articles', ['id' => 1, 'title' => 'Hello']);
Manticore::replace('articles', ['id' => 1, 'title' => 'Hello again']);
Manticore::delete('articles', 1);

$rows = Manticore::select('id', 'title')
    ->from('articles')
    ->match('title', 'hello')
    ->execute();
```

Or resolve the manager / connection from the container:

```php
$manticore = app(\Kvf77\Manticore\Laravel\Manticore::class);
$conn      = app(\Kvf77\Manticore\Drivers\ConnectionInterface::class);
```

## Testing

```bash
composer install
vendor/bin/phpunit
```

The test suite verifies query compilation and does **not** require a running
Manticore/Sphinx server.

## Credits & license

Originally based on [`foolz/sphinxql-query-builder`](https://github.com/FoolCode/SphinxQL-Query-Builder)
by foolz, licensed under Apache-2.0. This fork retains that license; see `LICENSE`
and `NOTICE` for the full text and the list of changes.
