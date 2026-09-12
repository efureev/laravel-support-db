# Roadmap

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Where the package goes next, and why in that order. Every gap below was checked against Laravel 13
and against a running PostgreSQL before it was written down — what the framework already does is
listed too, so none of it gets built twice.

## The shape of the work

The package exists to say the PostgreSQL things Laravel's builder cannot. The most valuable work
right now is not a new subject, though: it is **the missing halves of what already shipped**. The
package added `tsrange` and `daterange` without the constraint those types exist for, and it
offers two of the three `RETURNING` verbs.

Finish those first. New ground after.

The first item of this kind is already done — creating a partial unique index used to make
`upsert()` unusable, and [`onConflictWhere()`](query-builder.md) fixes that.

## v5.1 — close the holes in what is already sold

> `ON CONFLICT` against a partial unique index is done and documented under
> [Query builder](query-builder.md). Two left.

### `insertAndReturn()`

`updateAndReturn()` and `deleteAndReturn()` exist; the third of the trio does not. Laravel offers
only `insertGetId()` — one row, one column, the primary key. There is no way to insert several rows
and read back what the database generated for them.

```php
$rows = DB::table('orders')->insertAndReturn($values, 'id', 'created_at');
```

The `RETURNING` machinery is already written and already respects the connection's fetch mode; this
is the same path with a different verb.

### `check()`

Laravel's schema builder has no `CHECK` constraint at all — not in the Postgres grammar, not in any
other. A table's columns can be described but most of its invariants cannot, short of a raw
statement.

```php
$table->check('price_positive', fn ($c) => $c->where('price', '>', 0));
$table->dropCheck('price_positive');
```

The predicate builder used by partial indexes fits here unchanged, which also keeps one predicate
vocabulary across the package rather than two.

## v5.2 — exclusion constraints, and indexes that say more

### Exclusion constraints

The constraint that range columns exist for, and the strongest feature on this list. Verified:

```sql
alter table bookings add constraint bookings_no_overlap
  exclude using gist (room_id with =, during with &&);
```

```
ERROR: conflicting key value violates exclusion constraint "bookings_no_overlap"
DETAIL: Key (room_id, during)=(1, ["2026-01-03","2026-01-08")) conflicts with
        existing key (room_id, during)=(1, ["2026-01-01","2026-01-05")).
```

"No two bookings for the same room may overlap" is one line of DDL in PostgreSQL and inexpressible
in Laravel. It also composes with what the package already ships: `tsRange()` supplies the column,
and the scalar half of the constraint needs `btree_gist`, which
`Schema::createExtensionIfNotExists()` already installs.

```php
$table->exclusion('bookings_no_overlap')
    ->using('gist')
    ->with('room_id', '=')
    ->with('during', '&&')
    ->whereNull('cancelled_at');        // exclusion constraints take a predicate too
```

### Index expressiveness

The package already owns *indexes Laravel cannot express*. Four things are missing from that claim:

| Want | SQL | Today |
|---|---|---|
| Covering index | `create index … (room_id) include (during)` | nothing, anywhere |
| Column order and nulls | `(room_id desc nulls last, id)` | `index()` takes bare names only |
| Index on an expression | `create index … (lower(email))` | nothing |
| Operator class per column | `(payload jsonb_path_ops)` | only via `ginIndex()` |

Each is small, lands in the compilers that already exist, and pays for itself in a query plan. The
first two were checked against PostgreSQL 18 and behave as described.

## v5.3 — objects beyond tables

Views and extensions are already here. Types are the neighbouring subject Laravel can only read and
bulk-drop — `compileTypes()` and `compileDropAllTypes()` exist, `CREATE TYPE` does not:

```php
Schema::createEnumType('order_state', ['new', 'paid', 'shipped']);
Schema::createDomain('positive_int', 'integer', fn ($c) => $c->where('value', '>', 0));
Schema::dropTypeIfExists('order_state');
```

An enum type pairs naturally with the `BackedEnum` support the index predicates already have.

## v6 — physical layout and operations

Larger, more opinionated, and the first group where something may break:

- **Partitioning** — `partition by range/list/hash`, `attach partition`, `detach partition`
- **Row-level security** — `enable row level security`, policies. Relevant here: the package
  already does schema-qualified views for per-tenant data
- **Storage parameters** — `fillfactor`, per-table autovacuum tuning
- **`unlogged` tables** — for genuinely disposable data
- **`create statistics`** — extended statistics for correlated columns the planner mis-estimates

## Deliberately not on this list

Checked as native in Laravel 13, so building them here would be the duplication this package spent
a major version removing:

| Already native | Use |
|---|---|
| Generated columns | `$table->integer('c')->storedAs('a + b')`, `->virtualAs(…)` |
| `DISTINCT ON` | `$query->distinct('column')` on a pgsql connection |
| Lateral joins | `$query->joinLateral(…)` |
| Vector and full-text | `vector()`, `vectorIndex()`, `tsvector()` |
| Comments | `$table->comment(…)`, `$column->comment(…)` |
| Arbitrary column type | `$table->rawColumn('c', 'tstzrange')` |
| Unique index options | `->nullsNotDistinct()`, `->deferrable()`, `->online()` |

Common table expressions are **not** in Laravel, and are still not for this package: `WITH` is
ordinary SQL rather than a PostgreSQL extension, so it belongs to a cross-database package.

## How this is sequenced

| Version | Carries | Breaks |
|---|---|---|
| v5.1 | `insertAndReturn()`, `check()` — `ON CONFLICT` with a predicate shipped | nothing — all additive |
| v5.2 | exclusion constraints, covering indexes, column order, expression indexes | nothing |
| v5.3 | enum types, domains, composite types | nothing |
| v6 | partitioning, RLS, storage parameters, statistics | possibly |

Everything before v6 is additive, so a minor release carries it.

> Each item wants the same treatment as the rest of the package: a test that fails without it, the
> emitted SQL asserted exactly, and behaviour checked against a real server rather than recalled.
> See [Testing & contributing](contributing.md).
