# Migrating from foolz/sphinxql-query-builder

This package is a fork of `foolz/sphinxql-query-builder`. The query-building API is
intentionally compatible, so most code only needs a namespace change.

## 1. Namespace

Replace the vendor namespace everywhere:

| Old | New |
|-----|-----|
| `Foolz\SphinxQL\SphinxQL` | `Kvf77\Manticore\SphinxQL` |
| `Foolz\SphinxQL\Expression` | `Kvf77\Manticore\Expression` |
| `Foolz\SphinxQL\Facet` | `Kvf77\Manticore\Facet` |
| `Foolz\SphinxQL\MatchBuilder` | `Kvf77\Manticore\MatchBuilder` |
| `Foolz\SphinxQL\Helper` | `Kvf77\Manticore\Helper` |
| `Foolz\SphinxQL\Percolate` | `Kvf77\Manticore\Percolate` |
| `Foolz\SphinxQL\Drivers\Pdo\Connection` | `Kvf77\Manticore\Drivers\Pdo\Connection` |
| `Foolz\SphinxQL\Drivers\Mysqli\Connection` | `Kvf77\Manticore\Drivers\Mysqli\Connection` |
| `Foolz\SphinxQL\Drivers\*` / `Foolz\SphinxQL\Exception\*` | `Kvf77\Manticore\Drivers\*` / `Kvf77\Manticore\Exception\*` |

A project-wide search/replace of `Foolz\SphinxQL` → `Kvf77\Manticore` covers it.

Then remove the old dependency:

```bash
composer remove foolz/sphinxql-query-builder
composer require kvf77/manticore-query-builder
```

## 2. New features (additive, no migration needed)

- **`orWhere($column, $operator = null, $value = null)`** — OR-joined conditions.
- **Nested groups** — pass a `Closure` to `where()`/`orWhere()`; nests to any depth.
- **`regex($column, $pattern, $boolean = 'AND')`** — adds a `REGEX()` WHERE filter.
- **`valuesAmount(): int`** — number of staged INSERT/REPLACE value rows.
- **`Geo` helper** — `Geo::distance()`, `Geo::contains()`, `Geo::flattenPolygon()`
  for geo-distance and polygon search. See [Geo search](10-geo-search.md).

## 3. Behaviour changes to be aware of

- **`where()` first argument** may now be a `Closure` (nested group). If you relied
  on passing odd types as a column name, double-check.
- **`where()`/`orWhere()` second argument is now optional** (`$operator = null`) to
  support the `where('col', 'value')` and Closure forms uniformly.
- **PHP 8.3+ is required** and every file declares `strict_types=1`. Passing the
  wrong scalar type to a typed parameter now throws instead of silently coercing.
- **The core no longer depends on `illuminate/database`.** If you previously passed
  a Laravel `Illuminate\Database\Query\Expression` into `select()`, use this
  package's `Kvf77\Manticore\Expression` (or `SphinxQL::expr()`) instead — it
  stringifies itself, no Grammar needed.

## 4. If you came from a custom override (App\Libraries\SphinxQL)

If you had your own subclass with `orWhere` / nested `where` / `regex` /
`normalizeSelect`, those are now part of the core — delete the override and use the
package class directly. Note:

- `regex()` signature changed from `regex($column, $pattern, $half = false)` to
  `regex($column, $pattern, $boolean = 'AND')`. The unused `$half` flag is gone; the
  filter is now part of the unified WHERE chain (so it can be AND/OR-combined and
  appear among other conditions).
- `normalizeSelect()` / the `Illuminate\...\Grammar` hack is removed — pass
  `Kvf77\Manticore\Expression` for raw select columns.
- The upstream `execute()` behaviour is kept (it stores `last_result`); it does
  **not** clear `values`/`last_compiled` after running. If your override cleared
  them, call `reset()` (or start a new builder) explicitly between reuses.

## 5. Attribution

This fork preserves the original Apache-2.0 license. See `LICENSE` and `NOTICE`.
