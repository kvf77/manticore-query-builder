# Full-text MATCH

Full-text search is Manticore's reason for existing. It is expressed with
`MATCH()`, which the builder produces from `match()`.

## Simple match

```php
$qb->select()->from('articles')->match('title', 'manticore search');
// WHERE MATCH('(@title manticore search)')
```

- First argument: the field (or fields) to search.
- Second argument: the query text. It is **escaped** for you.
- Multiple `match()` calls are combined inside one `MATCH(...)`.

### Field targeting

```php
$qb->match('title', 'foo');           // @title foo
$qb->match(['title', 'body'], 'foo'); // @(title,body) foo
$qb->match('*', 'foo');               // search all fields
$qb->match(null, 'foo');              // also all fields
```

### MATCH combined with WHERE

`MATCH()` always comes first; ordinary `where()`/`orWhere()` conditions are glued
on after it with their boolean operator:

```php
$qb->select()->from('articles')
    ->match('title', 'hello')
    ->where('published', 1);
// WHERE MATCH('(@title hello)') AND published = 1
```

### Half-escaping

Pass `true` as the third argument to allow search operators (`-`, `|`, `"`) to pass
through, e.g. for a user-facing search box:

```php
$qb->match('title', 'fast -slow', true);
```

## The fluent MatchBuilder

For complex full-text expressions, pass a `Closure` as the field argument and use
the `MatchBuilder` API:

```php
use Kvf77\Manticore\MatchBuilder;

$qb->select()->from('articles')->match(function (MatchBuilder $m) {
    $m->match('hello')->orMatch('world');
});
// MATCH('(hello | world)')
```

### MatchBuilder reference

| Method | Produces |
|--------|----------|
| `match('a')` | `a` |
| `match('a b')` | `(a b)` |
| `orMatch('b')` | `\| b` |
| `maybe('b')` | `MAYBE b` |
| `not('b')` | `-b` |
| `field('title')->match('a')` | `@title a` |
| `field(['title','body'])->match('a')` | `@(title,body) a` |
| `field('body', 50)->match('a')` | `@body[50] a` (position limit) |
| `ignoreField('title')->match('a')` | `@!title a` |
| `phrase('exact words')` | `"exact words"` |
| `orPhrase('other words')` | `\| "other words"` |
| `proximity('a b', 5)` | `"a b"~5` |
| `quorum('a b c', 2)` | `"a b c"/2` |
| `match('a')->before('b')` | `a << b` |
| `match('a')->exact('b')` | `a =b` |
| `match('a')->boost(1.5)` | `a^1.5` |
| `match('a')->near('b', 3)` | `a NEAR/3 b` |
| `sentence('b')` | `SENTENCE b` |
| `paragraph('b')` | `PARAGRAPH b` |
| `zone('h1')` | `ZONE:(h1)` |
| `zonespan('h1')` | `ZONESPAN:(h1)` |

Builders can be nested via closures to build grouped expressions, e.g.
`$m->match('a')->maybe(fn ($s) => $s->match('b')->orMatch('c'))`.
