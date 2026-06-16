# Documentation

Full documentation for **kvf77/manticore-query-builder**.

1. [Getting started](01-getting-started.md) — install, connect, drivers, configuration
2. [SELECT & WHERE](02-select-where.md) — selecting, filtering, AND/OR, nested groups, REGEX, expressions, GROUP BY / ORDER BY / LIMIT / OPTION
3. [Full-text MATCH](03-fulltext-match.md) — `match()` and the fluent `MatchBuilder`
4. [Facets](04-facets.md) — single & multiple facets, computed facets, `executeBatch()`, reading facet results, the real-world "legend/stats" pattern
5. [Geo search](10-geo-search.md) — distance (`GEODIST`) and polygon (`GEOPOLY2D`/`CONTAINS`) search, preparing map coordinates
6. [Writing data](05-write-queries.md) — INSERT, REPLACE, UPDATE, DELETE, batched queries, bulk reindex
6. [Reading results](06-results.md) — iterating, fetching, counting
7. [Helpers & Percolate](07-helpers-percolate.md) — `SHOW META`, `CALL SNIPPETS`, percolate queries
8. [Laravel integration](08-laravel.md) — service provider, config, facade, manager
9. [Migrating from foolz/sphinxql-query-builder](09-migrating-from-foolz.md) — namespace and behaviour changes

> Throughout the docs, `$conn` is a connection created as shown in
> [Getting started](01-getting-started.md), and `$qb` is `new SphinxQL($conn)`.
