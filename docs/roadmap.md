# Roadmap

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Where the package goes next, and why in that order. Every gap below was checked against Laravel 13
and against a running PostgreSQL before it was written down — what the framework already does is
listed too, so none of it gets built twice.

## The shape of the work

The package exists to say the PostgreSQL things Laravel's builder cannot. The first two groups are
done: the missing halves of what had already shipped, and then the constraint that `tsrange` and
`daterange` exist for. What remains is new ground.

## v5.1 — shipped

Every item of this group is done: `onConflictWhere()` and `insertAndReturn()` under
[Query builder](query-builder.md), and `check()` under [Schema operations](schema.md).

## v5.2 — shipped

Exclusion constraints are under [Schema operations](schema.md); covering indexes and the
`Expression` route for column expressions, ordering and per-column operator classes are under
[Indexes](indexes.md).

Three of the four "index expressiveness" items turned out to need no new API at all: an
`Expression` in the column list already reaches PostgreSQL verbatim, so an expression index, a
sort direction and a per-column operator class were all expressible before this release and simply
undocumented. This list said "nothing, anywhere" for them, and that was wrong. Only `INCLUDE`
was genuinely missing.

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
| v5.1 | shipped — `ON CONFLICT` with a predicate, `insertAndReturn()`, `check()` | nothing |
| v5.2 | shipped — exclusion constraints, covering indexes | nothing |
| v5.3 | enum types, domains, composite types | nothing |
| v6 | partitioning, RLS, storage parameters, statistics | possibly |

Everything before v6 is additive, so a minor release carries it.

> Each item wants the same treatment as the rest of the package: a test that fails without it, the
> emitted SQL asserted exactly, and behaviour checked against a real server rather than recalled.
> See [Testing & contributing](contributing.md).
