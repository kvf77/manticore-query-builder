# Changelog

All notable changes to this package are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
