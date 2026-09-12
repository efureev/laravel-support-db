# Behaviour notes

[← Documentation](readme.md) · [Package readme](../readme.md)

---

The things that are easy to trip over, and what the framework already does without this package.

Things that are easy to trip over, gathered in one place.

**Server version floors.** `compression()` needs PostgreSQL 14, `nullsNotDistinct()` needs 15.
Everything else works from 13. All six versions run in CI.

**`CONCURRENTLY` cannot run in a transaction.** That applies to `online()` on an index and to
`refreshMaterializedView($view, true)`. A PostgreSQL migration runs inside a transaction by
default — `Migration::$withinTransaction` is `true`, and the Postgres grammar reports that it
supports schema transactions, so the migrator wraps it — which means both statements abort unless
the migration sets `public $withinTransaction = false;`.

**Partial unique indexes are indexes, not constraints.** PostgreSQL has no partial `UNIQUE`
constraint, so use `dropUniquePartial()` — `dropUnique()` will not find anything to drop.

**Identifiers are quoted, so case is preserved.** `createView('MyView', …)` creates a view named
`MyView`, and `hasView('MyView')` is what finds it. This differs from the framework's `hasView()`,
which folds case on both sides.

**`RETURNING` respects the fetch mode.** Rows come back the way `DB::select()` would return them,
and a `StatementPrepared` event is dispatched, so anything you have hooked onto that still fires.

**The driver resolver is process-wide.** The package registers itself for `pgsql` through
`Connection::resolverFor()`, which the framework's connection factory consults first. If another
package resolves the same driver, the last registration wins.

**Index predicates are DDL.** PostgreSQL takes no parameters in a `WHERE` on an index, so values
are escaped and inlined rather than bound. Only the documented value types are accepted; anything
else is rejected rather than guessed at.

## Already in Laravel 13

Some of what this package once existed for now ships with the framework. Reach for these first —
they are not duplicated here:

| Feature | Native form |
|---|---|
| Unique index options | `$table->unique($cols)->nullsNotDistinct()->deferrable()->initiallyImmediate()` |
| Index without locking the table | `$table->index($cols)->online()` |
| Index access method | `$table->index($cols, $name, 'gin')` |
| Arbitrary column type | `$table->rawColumn('c', 'tstzrange')` |
| Vector and full-text | `$table->vector('embedding', 3)`, `$table->vectorIndex('embedding')`, `$table->tsvector('doc')` |
| Table and column comments | `$table->comment('…')`, `$table->string('c')->comment('…')` |

What this package adds on top is everything *partial* — the index variants Laravel has no form for
— plus views, the `CREATE TABLE` variants, extensions, `RETURNING`, and the column types above.
