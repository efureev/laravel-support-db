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
