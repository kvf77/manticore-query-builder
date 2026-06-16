# Helpers & Percolate

## Helper — one-off statements

`Helper` wraps SphinxQL statements that don't need query building. Each method
returns a `SphinxQL` ready to `->execute()`.

```php
use Kvf77\Manticore\Helper;

$helper = new Helper($conn);

$meta = $helper->showMeta()->execute();        // SHOW META
$helper->showWarnings()->execute();            // SHOW WARNINGS
$helper->showStatus()->execute();              // SHOW STATUS
$helper->showVariables()->execute();           // SHOW VARIABLES
$helper->showTables('articles%')->execute();   // SHOW TABLES LIKE 'articles%'
$helper->describe('articles')->execute();      // DESCRIBE articles
$helper->showIndexStatus('articles')->execute();
```

### SHOW META after a search

`SHOW META` returns query metadata (total found, time, per-keyword stats). Run it
right after a search, on the same connection:

```php
$rows  = $qb->select()->from('articles')->match('title', 'foo')->execute();
$meta  = $helper->showMeta()->execute()->fetchAllAssoc();
$assoc = Helper::pairsToAssoc($meta); // ['total' => ..., 'time' => ..., ...]
```

### Index maintenance

```php
$helper->flushRtIndex('articles')->execute();
$helper->optimizeIndex('articles')->execute();
$helper->truncateRtIndex('articles')->execute();
$helper->attachIndex('disk_idx', 'rt_idx')->execute();
$helper->flushRamchunk('articles')->execute();
```

### Variables, snippets, keywords, UDFs

```php
$helper->setVariable('autocommit', 1)->execute();
$helper->callSnippets('the text to highlight', 'articles', 'search words')->execute();
$helper->callKeywords('test', 'articles')->execute();
$helper->createFunction('myudf', 'INT', 'mylib.so')->execute();
$helper->dropFunction('myudf')->execute();
```

## Percolate queries (`CALL PQ` / stored queries)

Percolate is **reverse search**. A normal index stores *documents* and you run a
*query* against them; a **percolate (PQ) index** stores the *queries*, and you feed
it a *document* to discover which of the stored queries that document matches.

This powers things like saved-search alerts (store each user's saved search once,
then check every incoming item against all of them in a single call), content
tagging/routing (which rules does this document trigger?), and real-time monitoring.

`Kvf77\Manticore\Percolate` wraps the two operations. A `Percolate` instance is
created with a connection and reused; each `execute()` clears its state for the next
call.

### Mode 1 — storing a query

You declare the query you want to keep, the percolate index it belongs to, and
optionally tags and an attribute filter, then execute. By default the stored query
text is escaped; you can opt out when you need raw full-text operators to survive.
Only one stored query is inserted per call.

### Mode 2 — matching documents (`CALL PQ`)

You switch to call mode, name the percolate index to query, supply one or more
documents, set any options, and execute. The result lists the stored queries that
the supplied document(s) match. The document input is flexible — a plain string, a
list of strings, a JSON object/array, or associative arrays — and the builder
detects the shape and sets the appropriate `docs_json` mode automatically.

### Method reference

| Method | Mode | Purpose |
|--------|------|---------|
| `insert(string $query, bool $noEscape = false)` | store | The full-text query to store; `$noEscape = true` keeps raw operators |
| `into(string $index)` | store | Target percolate index |
| `tags(array\|string $tags)` | store | Optional tags attached to the stored query |
| `filter(string $filter)` | store | Optional attribute filter for the stored query |
| `callPQ()` | match | Switch the builder into "match documents" mode |
| `from(string $index)` | match | Percolate index to match against |
| `documents(array\|string $documents)` | match | The document(s) to test against the stored queries |
| `options(array $options)` | match | `CALL PQ` options (see constants below) |
| `execute()` | both | Runs the built statement and returns the result set |
| `getLastQuery()` | both | The last compiled statement (debugging) |

Option constants for `options()`: `Percolate::OPTION_DOCS_JSON`,
`Percolate::OPTION_DOCS`, `Percolate::OPTION_VERBOSE`, `Percolate::OPTION_QUERY`
(each set to `1` or `0`).
