# Writing data (INSERT / REPLACE / UPDATE / DELETE)

## INSERT

```php
$qb->insert()
   ->into('articles')
   ->set(['id' => 1, 'title' => 'Hello', 'views' => 0])
   ->execute();
// INSERT INTO articles (id, title, views) VALUES (1, 'Hello', 0)
```

Equivalent column/value forms:

```php
$qb->insert()->into('articles')
   ->columns('id', 'title')
   ->values(1, 'Hello')
   ->execute();

// or one value at a time:
$qb->insert()->into('articles')->value('id', 1)->value('title', 'Hello');
```

### Multiple rows

Call `values()` more than once:

```php
$qb->insert()->into('articles')
   ->columns('id', 'title')
   ->values(1, 'First')
   ->values(2, 'Second');
// INSERT INTO articles (id, title) VALUES (1, 'First'), (2, 'Second')

$qb->valuesAmount(); // 2  — number of staged rows
```

### Multi-value attributes (MVA) and arrays

An array value becomes a parenthesised list (for MVA attributes):

```php
$qb->insert()->into('articles')->set(['id' => 1, 'tag_ids' => [10, 20, 30]]);
// ... VALUES (1, (10,20,30))
```

## REPLACE (upsert)

Same API as `insert()`, but **replaces** a document with the same id. This is the
idiomatic Manticore "insert or update":

```php
$qb->replace()
   ->into('articles')
   ->set(['id' => 1, 'title' => 'Hello again', 'views' => 5])
   ->execute();
// REPLACE INTO articles (id, title, views) VALUES (1, 'Hello again', 5)
```

> A real-time index `UPDATE` can only change attribute (numeric/MVA) columns, not
> full-text fields. To change text, re-`REPLACE` the whole document.

## UPDATE

```php
$qb->update('articles')
   ->value('views', 10)
   ->where('id', 1)
   ->execute();
// UPDATE articles SET views = 10 WHERE id = 1
```

Multiple columns:

```php
$qb->update('articles')->set(['views' => 10, 'rank' => 3])->where('id', 1);
```

`UPDATE` supports `where()` and `match()` exactly like `SELECT`.

## DELETE

```php
$qb->delete()->from('articles')->where('id', 1)->execute();
// DELETE FROM articles WHERE id = 1

$qb->delete()->from('articles')->where('author_id', 99)->execute();
// DELETE FROM articles WHERE author_id = 99
```

## Batched queries

Queue several statements and send them in one round-trip with `executeBatch()`:

```php
$last = (new SphinxQL($conn))->insert()->into('idx')->set(['id' => 1, 't' => 'a']);
$last = $last->enqueue((new SphinxQL($conn))->insert()->into('idx')->set(['id' => 2, 't' => 'b']));
$last = $last->enqueue((new SphinxQL($conn))->insert()->into('idx')->set(['id' => 3, 't' => 'c']));

$results = $last->executeBatch(); // MultiResultSet
```

`enqueue()` links queries together; `executeBatch()` compiles and runs the whole
queue. (This is also the mechanism behind faceted reads — see [Facets](04-facets.md).)

## Bulk indexing / reindex pattern

When you (re)index a whole table, you don't run one INSERT per row. Instead you
**reuse a single insert builder** and call `set()` once per entity — each call
stages another row, producing one efficient multi-row `INSERT` that you run once.

`set()` accumulates: the first call defines the columns, and every later call with
the same keys appends another value row.

```php
$insert = (new SphinxQL($conn))->insert()->into('articles');

foreach ($rows as $row) {
    $insert->set([
        'id'    => $row->id,
        'title' => $row->title,
        'views' => $row->views,
    ]);
}

$insert->execute();
// INSERT INTO articles (id, title, views) VALUES (1, 'A', 0), (2, 'B', 5), ...
```

### Guard against an empty INSERT with `valuesAmount()`

If a builder might receive **no** rows (e.g. an optional related table), executing
it would produce invalid SQL. Check `valuesAmount()` first:

```php
if ($contacts_sq->valuesAmount() > 0) {
    $contacts_sq->execute();
}
```

### Chunk large tables

Load and index in pages so you never hold the whole table in memory. Each chunk
gets its own fresh builders:

```php
use Kvf77\Manticore\SphinxQL;

$perPage = 100;
$pages   = (int) ceil(Stop::count() / $perPage);

// clear the index before a full rebuild (see Helper::truncateRtIndex / a raw query)
(new SphinxQL($conn))->query('TRUNCATE RTINDEX stops')->execute();

for ($page = 0; $page < $pages; $page++) {
    $rows = Stop::with('contacts')
        ->orderBy('id')
        ->offset($page * $perPage)
        ->limit($perPage)
        ->get();

    // one builder per target index, reused across the whole chunk
    $stops_sq    = (new SphinxQL($conn))->insert()->into('stops');
    $contacts_sq = (new SphinxQL($conn))->insert()->into('contacts');

    foreach ($rows as $stop) {
        $stops_sq->set(['id' => $stop->id, 'title' => $stop->title]);

        foreach ($stop->contacts as $contact) {
            $contacts_sq->set(['id' => $contact->id, 'phone' => $contact->phone]);
        }
    }

    $stops_sq->execute();

    // not every stop has contacts — skip the empty INSERT
    if ($contacts_sq->valuesAmount() > 0) {
        $contacts_sq->execute();
    }
}
```

This is exactly the shape of the production `reindex()` helpers: chunk by 100,
fan each entity into several index builders (`stops`, `contacts`, `full_site`),
then `execute()` each once per chunk, guarding the optional ones with
`valuesAmount()`.

> **Tip:** wrap a chunk in a [transaction](#transactions) (see below) if you want
> each page committed atomically.

## Transactions

Real-time indexes support transactions:

```php
$qb->transactionBegin();
try {
    $qb->insert()->into('idx')->set(['id' => 1, 't' => 'a'])->execute();
    $qb->insert()->into('idx')->set(['id' => 2, 't' => 'b'])->execute();
    $qb->transactionCommit();
} catch (\Throwable $e) {
    $qb->transactionRollback();
    throw $e;
}
```
