# Roadmap

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Where the package goes next, and why in that order. Every gap below was checked against Laravel 13
and against a running PostgreSQL before it was written down — what the framework already does is
listed too, so none of it gets built twice.

## The shape of the work

The package exists to say the PostgreSQL things Laravel's builder cannot. Everything planned here
has shipped: the missing halves of what had already been sold, the constraint that `tsrange` and
`daterange` exist for, types of one's own, and the physical layout of a table.

Nothing is outstanding. What follows is the record of how it went — including the two entries this
page got wrong, which are corrected in place rather than quietly dropped.

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

## v5.3 — shipped

Enum types, domains and composite types are under [Schema operations](schema.md), together with
adding a label to an existing enum and dropping either kind.

## v5.4 — shipped, and not the major it was planned as

Partitioning, row-level security, unlogged tables and storage parameters are under
[Schema operations](schema.md).

This group was written down as v6 because it looked like the one that would break something. It
does not: every item turned out to be a new method beside the existing ones, and the only existing
code touched was the create compiler, which gained two clauses. So it shipped as a minor, and the
version number here is corrected rather than kept for appearance.

`CREATE STATISTICS` shipped with it after all — see
[Extended statistics](schema.md#extended-statistics).

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
| v5.3 | shipped — enum types, domains, composite types | nothing |
| v5.4 | shipped — partitioning, RLS, unlogged, storage parameters, extended statistics | nothing |

Nothing on this page broke anything, so every group shipped as a minor.

> Each item wants the same treatment as the rest of the package: a test that fails without it, the
> emitted SQL asserted exactly, and behaviour checked against a real server rather than recalled.
> See [Testing & contributing](contributing.md).
