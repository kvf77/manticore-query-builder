# Changelog

All notable changes to this package are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-06-17

### Added
- Query listeners — `ConnectionInterface` implementations now expose `listen()`
  and `flushListeners()` (via the new `Kvf77\Manticore\Drivers\HasQueryListeners`
  interface), firing a `Kvf77\Manticore\Events\QueryExecuted` event (query string,
  elapsed milliseconds, result) after each executed query. This is the
  Manticore-layer counterpart to Laravel's `DB::listen()`, which does not see
  SphinxQL traffic. Exposed in Laravel via `Manticore::listen()`. See
  [docs/11-query-listener.md](docs/11-query-listener.md).
- `Kvf77\Manticore\Testing\FakeConnection` — a shippable connection test double
  that returns pre-canned result sets (`queueResult()`) and records executed
  queries (`executedQueries()`), backed by the new `ArrayResultSetAdapter`. Lets
  applications stub Manticore-backed code (e.g. search endpoints) without a live
  index. See [docs/12-testing.md](docs/12-testing.md).

### Notes
- Backward compatible. `FakeConnection` does not support `multiQuery()`/facets yet.

## [1.0.0] - 2026-06-16

### Added
- `orWhere()` and nested `where(Closure)` groups with arbitrary nesting depth.
- `regex()` — adds a Manticore `REGEX()` filter to the `WHERE` clause.
- `valuesAmount()` — number of value rows staged for an `INSERT`/`REPLACE`.
- `Geo` helper — `Geo::distance()`, `Geo::contains()` and `Geo::flattenPolygon()`
  build validated `GEODIST` / `GEOPOLY2D` + `CONTAINS` SELECT expressions (single
  simple polygon; GeoJSON flattening included).
- Laravel integration: service provider (auto-discovered), `config/manticore.php`,
  a `Manticore` manager and a `Manticore` facade.
- PHPUnit test suite covering query compilation (no live server required).

### Changed
- Forked from `foolz/sphinxql-query-builder` into the `Kvf77\Manticore` namespace.
- Unified and simplified the `WHERE` compiler (AND/OR, nested groups, regex).
- Modernised for PHP 8.3+ (`declare(strict_types=1)`).
- The core no longer depends on `illuminate/database`.

### Credits
Originally based on [foolz/sphinxql-query-builder](https://github.com/FoolCode/SphinxQL-Query-Builder)
by foolz, licensed under Apache-2.0. See `NOTICE`.
