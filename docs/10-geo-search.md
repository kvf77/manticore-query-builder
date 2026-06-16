# Geo search

Manticore can filter and sort by geography in two ways: by **distance** from a point
(`GEODIST`) and by **containment** inside a polygon (`GEOPOLY2D` + `CONTAINS`).

> **The golden rule:** geo functions can only appear in the **`SELECT`** clause in
> Manticore — never directly in `WHERE`. So you compute the geo value as an aliased
> column, then filter or sort on that **alias** in `WHERE`/`ORDER BY`. This is the
> single most important thing to get right.

## Recommended: the `Geo` helper

The `Kvf77\Manticore\Geo` factory builds these aliased expressions for you — with
coordinate validation and safe numeric rendering — so you don't hand-write the SQL:

```php
use Kvf77\Manticore\Geo;

// Distance
$qb->select('id', Geo::distance($lat, $lon, as: 'distance', unit: 'miles'))
   ->from('companies')
   ->where('distance', '<=', 50)
   ->orderBy('distance', 'asc');

// Polygon — pass GeoJSON (array or JSON string); it is flattened & validated for you
$qb->select('id', Geo::contains($geoJson, as: 'inside'))
   ->from('companies')
   ->where('inside', 1);

// Just the flat coordinate list, if you want to build the expression yourself
$flat = Geo::flattenPolygon($geoJson);   // "32.1839,34.8153,..."
```

`Geo::distance()` / `Geo::contains()` return [`Expression`](02-select-where.md#raw-expressions)
objects, so they drop straight into `select()` and compose with everything else.
The sections below show what they generate (and how to write it by hand if you need
a shape the helper doesn't cover).

## Distance search (`GEODIST`)

Find rows within N miles of a point and order them nearest-first:

```php
use Kvf77\Manticore\Expression;
use Kvf77\Manticore\SphinxQL;

$lat = 40.7;
$lon = -74.0;

$qb = (new SphinxQL($conn))
    ->select('id', new Expression("GEODIST(lat, lon, $lat, $lon, {in=degrees, out=miles}) AS distance"))
    ->from('companies')
    ->where('distance', '<=', 50)   // filter on the computed alias
    ->orderBy('distance', 'asc');   // sort on the computed alias

// SELECT id, GEODIST(lat, lon, 40.7, -74.0, {in=degrees, out=miles}) AS distance
// FROM companies WHERE distance <= 50 ORDER BY distance ASC
```

- `GEODIST(lat1, lon1, lat2, lon2, {options})` — distance between the row's
  `lat`/`lon` columns and the target point.
- `{in=degrees, out=miles}` — input is lat/lon in degrees, output in miles
  (use `out=km` for kilometres).
- Filtering (`where('distance', ...)`) and sorting (`orderBy('distance')`) work
  because `distance` is a real, aliased attribute of the result row.

A reusable helper that builds the SELECT fragment (as in the production code):

```php
function geoDistanceSelect(float $lat, float $lon, string $latCol = 'lat', string $lonCol = 'lon'): Expression
{
    return new Expression(
        "GEODIST($latCol, $lonCol, $lat, $lon, {in=degrees, out=miles}) AS distance"
    );
}

$qb->select('id', 'name', geoDistanceSelect($lat, $lon));
if ($radius) {
    $qb->where('distance', '<=', $radius)->orderBy('distance', 'asc');
}
```

## Polygon search (`GEOPOLY2D` + `CONTAINS`)

When the user draws a shape on a map, you get a polygon (a list of vertices) and ask
Manticore which rows fall **inside** it.

- `GEOPOLY2D(lat1, lon1, lat2, lon2, ...)` builds a polygon from a flat list of
  coordinate pairs.
- `CONTAINS(polygon, lat, lon)` returns `1` if the row's point is inside, else `0`.

The easy way is `Geo::contains($geoJson)` — it flattens and validates the polygon
for you (handles a `Polygon` or single-part `MultiPolygon` of any size, including
deeply nested and concave shapes):

```php
$qb->select('id', Geo::contains($geoJson))->from('companies')->where('inside', 1);
```

> **Supported shape: one simple polygon.** `GEOPOLY2D` models a single polygon —
> one outer ring, no holes. `Geo::contains()` / `Geo::flattenPolygon()` collect all
> the coordinate pairs they find, which is exactly right for a single-ring polygon
> (the common "draw an area on a map" case). They do **not** model **holes** (inner
> rings) or **multi-part** `MultiPolygon`s with several disjoint areas — those are
> rare and would need several `CONTAINS(...)` OR'd together, which you should build
> by hand for that specific case.

The manual equivalent, if you already have a flat coordinate string:

```php
$polygon = '40.7,-74.0,40.8,-74.1,40.75,-73.9'; // flat "lat,lon,lat,lon,..." list

$qb = (new SphinxQL($conn))
    ->select('id', new Expression("CONTAINS(GEOPOLY2D($polygon), lat, lon) AS inside"))
    ->from('companies')
    ->where('inside', 1); // keep only rows inside the polygon

// SELECT id, CONTAINS(GEOPOLY2D(40.7,-74.0,40.8,-74.1,40.75,-73.9), lat, lon) AS inside
// FROM companies WHERE inside = 1
```

### Preparing the polygon: flattening map coordinates

A map/GeoJSON polygon comes as **nested** coordinate arrays (a ring of points, or
rings of rings for multipolygons). `GEOPOLY2D` wants a single **flat** list of
numbers. `Geo::flattenPolygon($geoJson, precision: 4, lonLatInput: false)` does this
for you (recursively, any nesting depth, with validation), and `Geo::contains()`
calls it internally.

For reference, this is the equivalent hand-written helper (as used in production
before the library covered it) — it flattens any nesting depth and rounds each
coordinate to 4 decimals (~11 m, enough precision and keeps the query string compact):

```php
/**
 * Flattens nested geo coordinates (GeoJSON-style) into the flat
 * "c1,c2,c3,c4,..." list that GEOPOLY2D() expects.
 *
 * @param array $geo Decoded geo data, e.g. ['coordinates' => [[[lat, lon], ...]]]
 */
public static function convertToSphinx(array $geo): string
{
    if (empty($geo['coordinates'])) {
        throw new \InvalidArgumentException('Invalid geo data type.');
    }

    $result = [];

    // Walk up to three levels of nesting (point / ring / polygon / multipolygon)
    // and collect every coordinate pair, rounded to 4 decimals.
    foreach ($geo['coordinates'] as $line1) {
        if (!is_array($line1[0])) {
            $result[] = round($line1[0], 4);
            $result[] = round($line1[1], 4);
            continue;
        }
        foreach ($line1 as $line2) {
            if (!is_array($line2[0])) {
                $result[] = round($line2[0], 4);
                $result[] = round($line2[1], 4);
                continue;
            }
            foreach ($line2 as $line3) {
                if (!is_array($line3[0])) {
                    $result[] = round($line3[0], 4);
                    $result[] = round($line3[1], 4);
                }
            }
        }
    }

    if (empty($result)) {
        throw new \InvalidArgumentException('Invalid geo data type.');
    }

    return implode(',', $result);
}
```

Putting it together with a polygon coming from the request as JSON:

```php
$flat = GeoService::convertToSphinx(json_decode($filter['geometry'], true));

$qb->select('id', new Expression("CONTAINS(GEOPOLY2D($flat), lat, lon) AS inside"))
   ->from('companies')
   ->where('inside', 1);
```

> **Coordinate order matters.** `GEOPOLY2D` and `CONTAINS` expect **`lat, lon`**
> pairs. `convertToSphinx()` preserves the order of each pair as it appears in the
> input, so make sure your source coordinates are already `[lat, lon]` (some GeoJSON
> sources use `[lon, lat]` — swap them before flattening if so).

### Worked example: a real `MultiPolygon`

A polygon drawn on a map typically arrives as a GeoJSON `MultiPolygon`, whose
`coordinates` are nested **four** levels deep
(`coordinates → polygon → ring → point`):

```json
{
  "type": "MultiPolygon",
  "coordinates": [[[
    [32.1839088, 34.8152584],
    [32.1800428, 34.8161601],
    [32.1797700, 34.8184872],
    ... 25 more points ...
    [32.1839088, 34.8152584]
  ]]]
}
```

The three nested loops in `convertToSphinx()` reach the innermost `[lat, lon]`
pairs regardless of whether the input is a `Polygon` (3 levels) or a `MultiPolygon`
(4 levels). For the polygon above (28 vertices) it produces:

```
32.1839,34.8153,32.18,34.8162,32.1798,34.8185, ... ,32.1864,34.8154,32.1839,34.8153
```

which yields the query:

```sql
SELECT id, CONTAINS(GEOPOLY2D(32.1839,34.8153,32.18,34.8162, ... ,32.1839,34.8153), lat, lon) AS inside
FROM companies WHERE inside = 1
```

Note these coordinates are already in `[lat, lon]` order (latitude ~32°, longitude
~34°), so no swap is needed before flattening.

## Combining geo with full-text and facets

Because the geo value is just an aliased column, it composes with everything else —
`match()`, ordinary `where()` filters, `groupBy()`, and facets. When you build a
faceted "stats" query (see [Facets](04-facets.md)), append the geo expression to the
same `SELECT` string so the distance/inside attribute is available to the facets and
the main count alike.
