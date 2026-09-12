# Query builder

[← Documentation](readme.md) · [Package readme](../readme.md)

---

RETURNING on UPDATE and DELETE — the rows a write touched, in one round trip.

## RETURNING on UPDATE and DELETE

PostgreSQL can hand back the rows a write touched. One round trip instead of select-then-write,
and — unlike reading first — no window in which another transaction changes the set underneath you.

```php
// on the query builder
$updated = DB::table('orders')
    ->where('state', 'new')
    ->updateAndReturn(['state' => 'paid'], 'id', 'total');

$deleted = DB::table('sessions')
    ->where('expires_at', '<', now())
    ->deleteAndReturn('id', 'user_id');
```

```php
// on Eloquent, via macros
$updated = Order::where('state', 'new')->updateAndReturn(['state' => 'paid'], 'id');
$deleted = Order::where('state', 'cancelled')->deleteAndReturn('id');
```

Both take the columns as a variadic list; pass `*` for the whole row.

> **Eloquent versus `toBase()`.** Through Eloquent the model's `updated_at` is maintained as
> usual. `Model::toBase()` drops to the query builder and writes exactly the columns you pass —
> which is what you want for a sweep that should not touch timestamps.

Rows come back in the connection's configured fetch mode, the same as `DB::select()` — `stdClass`
objects unless you have changed it, and a `StatementPrepared` event is dispatched either way.

---

## Upserting against a partial unique index

A partial unique index is the package's answer to "unique among the rows that count", and it makes
the framework's `upsert()` unusable on its own:

```php
$table->uniquePartial('email')->whereNull('deleted_at');

DB::table('users')->upsert([['email' => 'a@x.io', 'name' => 'B']], ['email'], ['name']);
```

```sql
SQLSTATE[42P10]: there is no unique or exclusion constraint
matching the ON CONFLICT specification
```

PostgreSQL will not infer a *partial* index from the conflict columns alone — the index predicate
has to be repeated, and has to match. `onConflictWhere()` supplies it, in the same vocabulary the
index was declared with:

```php
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;

DB::table('users')
    ->onConflictWhere(static fn (PartialBuilder $where) => $where->whereNull('deleted_at'))
    ->upsert([['email' => 'a@x.io', 'name' => 'B']], ['email'], ['name']);
```

```sql
insert into "users" ("email", "name") values (?, ?)
    on conflict ("email") where ("deleted_at" is null)
    do update set "name" = "excluded"."name"
```

Every predicate from [Index predicates](indexes.md) works here, because it is the same builder.
Repeated calls accumulate rather than replace.

> **The predicate must match the index, not merely be true.** PostgreSQL compares the two and
> refuses a conflict target it cannot map onto an existing index — a predicate of
> `whereNotNull('deleted_at')` against an index built `where deleted_at is null` fails exactly as
> an absent one does. Write the same predicate in both places.
