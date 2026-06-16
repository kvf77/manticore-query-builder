# Facets

A **facet** runs an aggregation alongside your main search, in the *same* query.
This is how you build "legends" / counters like *"how many vehicles are active vs
inactive, per status, per type"* — all in a single round-trip to Manticore.

A faceted query produces **multiple result sets**: one for the main `SELECT`, then
one per `FACET`. Because of that you must run it with **`executeBatch()`** (which
returns a `MultiResultSet`), not `execute()`.

```
SELECT count(distinct id) AS count_record FROM trips WHERE ...
FACET status        LIMIT 0, 100
FACET vehicle_type  LIMIT 0, 100
```

## A single facet

```php
use Kvf77\Manticore\Facet;
use Kvf77\Manticore\SphinxQL;

$qb = (new SphinxQL($conn))
    ->select('count(*) AS cnt')
    ->from('articles')
    ->facet((new Facet())->facet('category_id'));
// SELECT count(*) AS cnt FROM articles FACET category_id
```

`facet()` on the builder attaches a `Facet` object; you can attach as many as you
like and they are appended to the query in order.

## Facet options

The `Facet` object mirrors the SphinxQL `FACET` clause:

```php
(new Facet())
    ->facet('brand')                 // FACET brand
    ->by('brand_id')                 // BY brand_id      (group expression)
    ->orderBy('count(*)', 'desc')    // ORDER BY count(*) DESC
    ->limit(0, 100);                 // LIMIT 0, 100
```

> **Gotcha:** a `FACET` defaults to returning only **20** rows. If a dimension has
> more distinct values than that you will silently lose buckets. Set
> `->limit(0, 100)` (or higher) on every facet, exactly as the real-world code does.

### Multiple columns / functions

```php
(new Facet())->facet('idCategory', 'year');
// FACET idCategory, year

(new Facet())->facet(['categories' => 'idCategory', 'year', 'type' => 'idType']);
// FACET idCategory AS categories, year, idType AS type

(new Facet())->facetFunction('INTERVAL', ['price', 20, 50, 100]);
// FACET INTERVAL(price,20,50,100)
```

## Computed facets with an alias (`expression AS alias`)

This is the key technique for "stats/legend" queries. To facet on a **computed
boolean/expression** and get it back under a known key, wrap the expression in an
`Expression` (so it is **not** quoted) and include `AS alias`:

```php
use Kvf77\Manticore\Expression;

(new Facet())
    ->facet(new Expression("INTEGER(next_stop_time <= $now) AS should_arrive"))
    ->limit(0, 100);
// FACET INTEGER(next_stop_time <= 1700000000) AS should_arrive LIMIT 0, 100
```

The bucket for the value `1` is "matches the condition", `0` is "doesn't".

## Running a faceted query

```php
$multi = $qb->executeBatch();          // MultiResultSet (main + one per facet)
```

> Use `executeBatch()`, **not** `execute()`. `execute()` returns only the first
> result set; the facet result sets would be lost.

## Reading facet results

Iterate the `MultiResultSet` with `getNext()`. Each result set is a list of rows;
a facet row looks like `['<facet_column>' => <value>, 'count(*)' => <n>]`. The
main result set carries your aggregate (here `count_record`).

The package ships a ready-made flattener on the Laravel `Manticore` manager,
`facetResults()`, which turns the multi-result-set into a simple associative array.
Here it is as a standalone function so you can use it without Laravel too:

```php
use Kvf77\Manticore\Drivers\MultiResultSetInterface;

function flattenFacets(MultiResultSetInterface $res): array
{
    $stat = [];

    while ($item = $res->getNext()) {
        foreach ($item as $row) {
            // a normal facet row: ['status' => 'active', 'count(*)' => 42]
            if (isset($row['count(*)'])) {
                $stat[array_key_first($row)][current($row)] = $row['count(*)'];
            }
            // the main SELECT row: ['count_record' => 1234]
            if (isset($row['count_record'])) {
                $stat['total'] = $row['count_record'];
            }
        }
    }

    return $stat;
}
```

Resulting shape:

```php
[
    'total'        => 1234,                  // from count(distinct id) AS count_record
    'status'       => ['active' => 900, 'inactive' => 334],
    'vehicle_type' => ['truck' => 800, 'van' => 434],
    'should_arrive'=> [0 => 1100, 1 => 134], // computed facet: 0/1 buckets
]
```

In Laravel this is just:

```php
use Kvf77\Manticore\Laravel\Facades\Manticore;

$stats = Manticore::facetResults($qb->executeBatch());
```

## Real-world pattern: a reusable "legend/stats" helper

This is the pattern used in production (`doGetLegendsStat`). It accepts a list of
fields — each either a **plain column** (numeric key) or an **`alias => expression`**
(computed) — applies the page's WHERE filters, attaches one facet per field, and
flattens the result:

```php
use Kvf77\Manticore\Expression;
use Kvf77\Manticore\Laravel\Facades\Manticore;
use Kvf77\Manticore\SphinxQL;

/**
 * @param array $fields  e.g. ['status', 'vehicle_type', 'should_arrive' => "INTEGER(eta <= $now)"]
 * @param array $filter  WHERE filters for the page (your own applyFilters())
 */
function legendStats(array $fields, array $filter, string $index, string $select): array
{
    /** @var SphinxQL $qb */
    $qb = Manticore::select($select)->from([$index]);

    applyFilters($qb, $filter); // your function: turns $filter into ->where(...) calls

    foreach ($fields as $key => $field) {
        if (is_string($key)) {
            // 'alias' => 'expression'  →  FACET <expression> AS <alias>
            $qb->facet((new Facet())->facet(new Expression("$field AS $key"))->limit(0, 100));
        } else {
            // plain column            →  FACET <column>
            $qb->facet((new Facet())->facet($field)->limit(0, 100));
        }
    }

    return Manticore::facetResults($qb->executeBatch());
}
```

Call site (mixed simple + computed facets, just like `TripMonitorService::getLegendsStat`):

```php
$now    = now()->timestamp;
$in30m  = $now + 1800;

$stats = legendStats(
    [
        'late_eta',                                                  // plain column facet
        'broker_notification',                                       // plain column facet
        'should_arrive' => "INTEGER(next_stop_time >= $now AND next_stop_time <= $in30m)",
        'arrived'       => "INTEGER(vehicle_status = 'arrived' OR vehicle_status = 'processed')",
        'late_by'       => "INTEGER(next_stop_time > 0 AND next_stop_time <= $now)",
        'notes'         => "INTEGER(notes_amount > 0)",
    ],
    $filter,
    'trips',
    'count(distinct id) AS count_record'
);
```

### Geo-distance in facet queries

Manticore can only compute `GEODIST()` in the `SELECT`, not in `WHERE`. So for a
distance-aware legend, append the distance expression to the select string:

```php
$select = 'count(distinct id) AS count_record, '
    . 'GEODIST(start_lat, start_lon, 40.7, -74.0, {in=degrees, out=miles}) AS distance';
```

See [Geo search](10-geo-search.md) for the full distance and polygon search patterns.

### Getting correct counters per dimension

When a facet dimension is *itself* being filtered, its own counts get skewed
(everything collapses to the selected value). The production code fixes this by
re-running each affected facet with that dimension **excluded** from the filter:

```php
foreach ($filter as $segment => $value) {
    if (! array_key_exists($segment, $fields) && ! in_array($segment, $fields, true)) {
        continue; // not one of our facet dimensions
    }

    $subFilter = $filter;
    unset($subFilter[$segment]);            // drop the dimension's own filter

    $subQb = Manticore::select($select)->from([$index]);
    applyFilters($subQb, $subFilter);

    if (is_string($key = array_search($segment, array_keys($fields), true)) && isset($fields[$segment])) {
        $expr = $fields[$segment];
        $subQb->facet((new Facet())->facet(new Expression("$expr AS $segment"))->limit(0, 100));
    } else {
        $subQb->facet((new Facet())->facet($segment)->limit(0, 100));
    }

    $sub = Manticore::facetResults($subQb->executeBatch());

    unset($stats[$segment]);
    if (! empty($sub[$segment])) {
        $stats[$segment] = $sub[$segment]; // replace with the unbiased counts
    }
}
```

This gives each legend the count it would have *if you toggled that filter*, which
is what faceted-search UIs expect.
