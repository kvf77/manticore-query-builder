# SELECT & WHERE

## SELECT

```php
$qb->select();                       // SELECT *
$qb->select('id', 'title');          // SELECT id, title
$qb->select(['id', 'title']);        // SELECT id, title (array form)
```

You can select computed columns and aggregates as raw strings:

```php
$qb->select('id', 'WEIGHT() AS w', 'count(distinct author_id) AS authors');
```

To change the selected columns later without resetting the rest of the query, use
`setSelect()`; read them back with `getSelect()`.

## FROM (indexes)

Manticore can query several indexes at once, so `from()` is variadic:

```php
$qb->from('articles');                 // FROM articles
$qb->from('articles', 'comments');     // FROM articles, comments
$qb->from(['articles', 'comments']);   // same, array form
```

`from()` also accepts a sub-query (another `SphinxQL` instance or a `Closure`):

```php
$qb->from(function (SphinxQL $sub) {
    $sub->select('id')->from('articles')->where('published', 1);
});
// FROM (SELECT id FROM articles WHERE published = 1)
```

## WHERE

Two- and three-argument forms are supported:

```php
$qb->where('status', 'active');          // status = 'active'  (operator defaults to =)
$qb->where('age', '>=', 18);             // age >= 18
$qb->where('id', 5);                     // id = 5
```

> **Note:** `id` is never quoted — Manticore requires the document id to be a bare
> integer. The builder handles this for you.

### Operators

| Form | Compiles to |
|------|-------------|
| `where('a', 1)` | `a = 1` |
| `where('a', '>=', 1)` | `a >= 1` |
| `where('a', '!=', 1)` | `a != 1` |
| `where('id', 'IN', [1, 2, 3])` | `id IN (1, 2, 3)` |
| `where('id', 'NOT IN', [1, 2])` | `id NOT IN (1, 2)` |
| `where('price', 'BETWEEN', [10, 100])` | `price BETWEEN 10 AND 100` |

### AND / OR

Multiple `where()` calls are joined with **AND**. Use `orWhere()` for **OR**:

```php
$qb->select()->from('idx')
    ->where('a', 1)
    ->orWhere('b', 2);
// WHERE a = 1 OR b = 2
```

### Nested groups

Pass a `Closure` as the only argument to `where()` / `orWhere()` to build a
parenthesised group. Groups can be nested to any depth:

```php
$qb->select()->from('idx')
    ->where('active', 1)
    ->where(function (SphinxQL $q) {
        $q->where('type', 'a')->orWhere('type', 'b');
    });
// WHERE active = 1 AND (type = 'a' OR type = 'b')
```

```php
$qb->where('a', 1)->where(function (SphinxQL $q) {
    $q->where('b', 2)->orWhere(function (SphinxQL $q2) {
        $q2->where('c', 3)->orWhere('d', 4);
    });
});
// WHERE a = 1 AND (b = 2 OR (c = 3 OR d = 4))
```

### REGEX (Manticore)

`regex()` adds a `REGEX()` filter to the WHERE clause. It joins with AND by
default; pass `'OR'` as the third argument to OR it in:

```php
$qb->select()->from('idx')->regex('title', '.*foo.*');
// WHERE REGEX(title, '.*foo.*')

$qb->where('active', 1)->regex('title', 'foo');
// WHERE active = 1 AND REGEX(title, 'foo')
```

## Raw expressions

Values are quoted/escaped automatically. To inject a raw, unescaped fragment
(a function call, a column reference, `CURRENT_TIMESTAMP`, …) wrap it in an
`Expression`:

```php
use Kvf77\Manticore\Expression;
use Kvf77\Manticore\SphinxQL;

$qb->where('updated', '>', new Expression('CURRENT_TIMESTAMP'));
// WHERE updated > CURRENT_TIMESTAMP

// Shorthand helper:
$qb->where('updated', '>', SphinxQL::expr('CURRENT_TIMESTAMP'));
```

Expressions are also how you build computed FACET columns — see [Facets](04-facets.md).

## GROUP BY

```php
$qb->groupBy('category_id');                 // GROUP BY category_id
$qb->groupBy('category_id')->groupBy('year'); // GROUP BY category_id, year
$qb->groupNBy(3);                             // turns it into GROUP 3 BY ...
$qb->withinGroupOrderBy('rank', 'desc');      // WITHIN GROUP ORDER BY rank DESC
$qb->having('cnt', '>', 1);                   // HAVING cnt > 1
```

## ORDER BY

```php
$qb->orderBy('id');                      // ORDER BY id        (no keyword; Manticore sorts ascending)
$qb->orderBy('id', 'desc');              // ORDER BY id DESC
$qb->orderBy('a')->orderBy('b', 'desc'); // ORDER BY a, b DESC
```

When you omit the direction, **no `ASC`/`DESC` keyword is emitted** — Manticore
defaults to ascending. This matters for the random-order case below.

### Random order — `ORDER BY RAND()`

Manticore supports random ordering via `RAND()`, but it must be written **without a
direction** (adding `ASC`/`DESC` is a syntax error). So call `orderBy()` with the
expression only, and no second argument:

```php
$qb->select()->from('companies')->orderBy('rand()');
// SELECT * FROM companies ORDER BY rand()
```

> Be aware that `RAND()` reshuffles on every query, so it is **not stable across
> paginated requests** — the same row can appear on two different pages, or none.
> Use it for "give me some random results", not for deep, consistent pagination.

### Sorting, pagination and `max_matches`

Manticore only sorts within the **`max_matches`** window (default **1000**). If you
sort and then page beyond the first 1000 results, deeper pages will be empty or
wrong unless you raise `max_matches` to cover the offset you intend to reach. The
production code computes it from the page being requested:

```php
$page   = $filter['page'];    // zero-based
$amount = $filter['amount'];  // page size

$qb->select($select)->from(['companies'])
   ->option('max_matches', (int) (($amount * $page) + $amount + 1))
   ->limit($amount * $page, $amount)   // LIMIT <offset>, <page size>
   ->orderBy('rand()');                // or 'company_id', or 'is_recommended' DESC, ...
```

Switching the sort by a user-supplied value is just a `switch`/`match` over
`orderBy()` calls:

```php
match ((string) $order) {
    '0'    => $qb->orderBy('company_id', 'asc'),
    '1'    => $qb->orderBy('is_recommended', 'desc'),
    'rand' => $qb->orderBy('rand()'),
    default => $qb,
};
```

## LIMIT & OFFSET

```php
$qb->limit(20);        // LIMIT 0, 20
$qb->limit(40, 20);    // LIMIT 40, 20  (offset 40, 20 rows)
$qb->offset(40)->limit(20);
```

> Remember Manticore's `max_matches` (default **1000**) caps how many rows the
> engine will consider; raise it via `OPTION` or your index config if you page deep.

## OPTION (Manticore-specific)

```php
$qb->option('ranker', 'bm25');          // OPTION ranker = 'bm25'
$qb->option('max_matches', 5000);       // OPTION max_matches = 5000
$qb->option('field_weights', ['title' => 10, 'body' => 1]);
// OPTION field_weights = (title=10, body=1)
```

## Resetting parts of a query

`resetWhere()`, `resetMatch()`, `resetGroupBy()`, `resetOrderBy()`,
`resetHaving()`, `resetOptions()`, `resetWithinGroupOrderBy()` and `resetFacets()`
clear individual clauses; `reset()` clears everything.
