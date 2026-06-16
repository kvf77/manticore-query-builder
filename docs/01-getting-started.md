# Getting started

## Requirements

- PHP **8.3+**
- One of: `ext-pdo` (default) or `ext-mysqli`
- A running [Manticore Search](https://manticoresearch.com/) (or Sphinx) instance
  listening on the MySQL protocol port (default **9306**)

## Install

```bash
composer require kvf77/manticore-query-builder
```

## Creating a connection

The query builder talks to the server through a **connection** object. Two drivers
are bundled — pick one.

### PDO driver (recommended)

```php
use Kvf77\Manticore\Drivers\Pdo\Connection;

$conn = new Connection();
$conn->setParams([
    'host' => '127.0.0.1',
    'port' => 9306,
]);
```

### MySQLi driver

```php
use Kvf77\Manticore\Drivers\Mysqli\Connection;

$conn = new Connection();
$conn->setParams([
    'host' => '127.0.0.1',
    'port' => 9306,
]);
```

### Unix socket

```php
$conn->setParams(['socket' => 'unix:/var/run/manticore/manticore.sock']);
// or
$conn->setParams(['host' => 'unix:/var/run/manticore/manticore.sock']);
```

`setParams()` accepts `host`, `port`, `socket`, and (MySQLi only) `options`
(an array of MySQLi connection options). The connection is **lazy**: it is not
opened until the first query runs.

## Your first query

```php
use Kvf77\Manticore\SphinxQL;

$result = (new SphinxQL($conn))
    ->select('id', 'title')
    ->from('articles')
    ->where('published', 1)
    ->limit(10)
    ->execute();

foreach ($result as $row) {
    echo $row['id'].': '.$row['title'].PHP_EOL;
}
```

## Inspecting the compiled query

Every builder can show the SQL it will run without executing it — invaluable for
debugging:

```php
$qb = (new SphinxQL($conn))->select()->from('articles')->where('id', 5);

echo $qb->compile()->getCompiled();
// SELECT * FROM articles WHERE id = 5
```

`compile()` only builds the string; `execute()` builds **and** runs it.

## Reusing a builder

`select()`, `insert()`, `replace()`, `update()` and `delete()` each call
`reset()` internally, so a single `SphinxQL` instance can be reused for a new
query. If you keep a long-lived instance, start each new query with one of those
methods.

> **Laravel users:** you usually won't create connections by hand — the bundled
> service provider does it for you from config/env. See [Laravel integration](08-laravel.md).
