# Reading results

`execute()` returns a `ResultSet` (implementing `ResultSetInterface`).
`executeBatch()` returns a `MultiResultSet` — a sequence of `ResultSet`s.

## ResultSet

### Iterate

A `ResultSet` is iterable; each row is an associative array:

```php
$result = $qb->select()->from('articles')->where('published', 1)->execute();

foreach ($result as $row) {
    echo $row['id'], ' ', $row['title'], PHP_EOL;
}
```

### Fetch all at once

```php
$rows = $result->fetchAllAssoc(); // array of associative arrays
$rows = $result->fetchAllNum();   // array of numeric-indexed arrays
```

### Fetch one row at a time

```php
while ($row = $result->fetchAssoc()) {
    // ...
}
// or numeric:
$row = $result->fetchNum();
```

### Count & affected rows

```php
$result->count();           // number of rows in a SELECT result
$result->getAffectedRows(); // rows affected by INSERT/REPLACE/UPDATE/DELETE
```

### Random access

```php
if ($result->hasRow(3)) {
    $row = $result->toRow(3)->fetchAssoc();
}
$result[0]; // ArrayAccess: same as toRow(0)->fetchAssoc()
```

### Freeing

```php
$result->freeResult(); // release the server-side result when done
```

## MultiResultSet

Returned by `executeBatch()` (and by faceted reads). Walk it with `getNext()`:

```php
$multi = $qb->executeBatch();

while ($set = $multi->getNext()) {   // each $set is a ResultSet (or its rows)
    foreach ($set as $row) {
        // ...
    }
}
```

It is also countable and array-accessible after `store()`:

```php
$multi->store();      // pull everything into PHP memory
count($multi);        // number of result sets
$first = $multi[0];
```

See [Facets](04-facets.md) for the canonical way to flatten a faceted
`MultiResultSet` into a stats array.

## DML return values

For `INSERT`/`REPLACE`/`UPDATE`/`DELETE`, the returned `ResultSet` reports work
done via `getAffectedRows()`; there are no data rows to iterate.
