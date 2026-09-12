# Indexes

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Partial and unique-partial indexes, the predicates that define them, the modifiers that change how they are built, and GIN operator classes.

## Partial indexes

A [partial index](https://www.postgresql.org/docs/current/indexes-partial.html) covers only the
rows matching a predicate. It is smaller, cheaper to maintain, and — in the unique case — the
standard way to express "unique among the rows that count".

```php
$table->partial('code')->whereNull('deleted_at');
// create index "docs_code_partial" on "docs" ("code") where ("deleted_at" is null)

$table->uniquePartial('email')->whereNull('deleted_at');
// create unique index "docs_email_unique" on "docs" ("email") where ("deleted_at" is null)
```

Both accept the same three arguments and both take one column or several:

```php
partial(array|string $columns, ?string $index = null, ?string $algorithm = null)
uniquePartial(array|string $columns, ?string $index = null, ?string $algorithm = null)
```

Without an explicit name the index is called `{table}_{columns}_partial` or
`{table}_{columns}_unique`, following the framework's own convention.

**Dropping.** Pass the columns to let the name be derived, or the index name itself if you chose
one:

```php
$table->dropPartial(['code']);              // drop index "docs_code_partial"
$table->dropUniquePartial(['email']);       // drop index "docs_email_unique"
$table->dropPartial('docs_reachable_ix');   // drop index "docs_reachable_ix"
```

> `dropUnique()` does not work on a partial unique index. PostgreSQL has no partial `UNIQUE`
> *constraint*, so `uniquePartial()` creates a plain unique *index* with no constraint attached,
> and there is nothing for `ALTER TABLE … DROP CONSTRAINT` to find.

## Index predicates

The predicate is built fluently. Every method also takes a trailing `$boolean`, and each has an
`or` spelling so a disjunction reads as one.

| Method | Emits |
|---|---|
| `where($column, $operator, $value)` | `("size" > 10)` |
| `whereRaw($sql, $bindings = [])` | `(lower(code) = 'x')` |
| `whereBool($column, $value)` | `("published" is true)` |
| `whereTrue($column)` / `whereFalse($column)` | `("published" is true)` / `… is false` |
| `whereNull($column)` / `whereNotNull($column)` | `("deleted_at" is null)` / `… is not null` |
| `whereColumn($first, $operator, $second)` | `("created_at" < "updated_at")` |
| `whereIn($column, $values)` / `whereNotIn(…)` | `("state" in ('a','b'))` / `… not in (…)` |
| `whereBetween($column, [$from, $to])` / `whereNotBetween(…)` | `("n" between 1 and 9)` / `… not between …` |

Each has an `orWhere…` counterpart: `orWhere`, `orWhereRaw`, `orWhereBool`, `orWhereTrue`,
`orWhereFalse`, `orWhereNull`, `orWhereNotNull`, `orWhereColumn`, `orWhereIn`, `orWhereNotIn`,
`orWhereBetween`, `orWhereNotBetween`.

```php
$table->partial('code', 'docs_reachable')
    ->whereNull('deleted_at')
    ->orWhereTrue('is_pinned')
    ->whereFalse('is_draft');
// … where ("deleted_at" is null) or ("is_pinned" is true) and ("is_draft" is false)
```

A leading `or` is stripped, so the first predicate may use either spelling.

**Values.** Accepted: `string`, `int`, `float`, `bool`, `null`, `BackedEnum` (its value is used),
`DateTimeInterface` (ISO-8601), and `Stringable`. They are escaped and inlined — an index
predicate is part of the DDL, and PostgreSQL takes no parameters there. Anything else is rejected
rather than coerced.

```php
$table->partial('state')->where('state', '=', OrderState::Paid);   // backed enum
$table->partial('at')->where('at', '>', new DateTimeImmutable('2026-01-01'));
```

`whereRaw()` uses `?` placeholders, bound the same way and inlined the same way:

```php
$table->partial('code')->whereRaw('lower(code) = ?', ['abc']);
// … where (lower(code) = 'abc')
```

## Index modifiers

These chain onto `partial()` and `uniquePartial()` in any position — before or after the
predicates, it makes no difference.

| Modifier | Effect |
|---|---|
| `algorithm(string $method)` | `using gin` — the access method; also the third constructor argument |
| `online(bool $value = true)` | `create index concurrently` — builds without a write lock |
| `nullsNotDistinct(bool $value = true)` | `nulls not distinct` — unique only, PostgreSQL >= 15 |

```php
$table->partial('tags', 'docs_tags_gin', 'gin')->whereNotNull('tags');
// identical:
$table->partial('tags', 'docs_tags_gin')->algorithm('gin')->whereNotNull('tags');
```

```php
$table->partial('code')->whereNull('deleted_at')->online();
// create index concurrently "docs_code_partial" on "docs" ("code") where ("deleted_at" is null)
```

```php
$table->uniquePartial(['team', 'seat'])->nullsNotDistinct()->whereNull('deleted_at');
// create unique index "docs_team_seat_unique" on "docs" ("team", "seat")
//     nulls not distinct where ("deleted_at" is null)
```

> **`online()` and transactions.** PostgreSQL refuses `CREATE INDEX CONCURRENTLY` inside a
> transaction block. Laravel does not wrap migrations in one by default; if yours opts in with
> `$withinTransaction = true`, this cannot be used there.
>
> **`nullsNotDistinct()` is unique-only.** PostgreSQL parses the clause on a non-unique index and
> then ignores it, so `partial()` throws rather than emitting something that does nothing.
>
> **`UNIQUE` is btree-only.** PostgreSQL supports unique indexes on btree alone, so an
> `algorithm()` other than `btree` on `uniquePartial()` is rejected by the server.

## GIN indexes and operator classes

`ginIndex()` is a shorthand for a GIN index — the access method for arrays, `jsonb` and full-text
search.

```php
$table->textArray('tags');
$table->ginIndex('tags');
// create index "docs_tags_index" on "docs" using gin ("tags")
```

A third argument names an
[operator class](https://www.postgresql.org/docs/current/indexes-opclass.html), which is where GIN
indexes earn their keep:

```php
ginIndex(array|string $columns, ?string $name = null, ?string $operatorClass = null)
```

```php
$table->jsonb('payload');
$table->ginIndex('payload', 'docs_payload_gin', 'jsonb_path_ops');
// create index "docs_payload_gin" on "docs" using gin ("payload" jsonb_path_ops)
```

| Operator class | For | Buys you |
|---|---|---|
| `jsonb_path_ops` | `jsonb` | A smaller, faster index for containment (`@>`) — at the cost of key-existence operators |
| `gin_trgm_ops` | `text` (needs `pg_trgm`) | Indexed `LIKE '%…%'` and similarity search |
| `array_ops` | arrays | The default; rarely named explicitly |

The framework accepts an operator class on spatial and vector indexes only — its `index()` takes no
such argument, and `compileIndex()` discards one that arrives another way.
