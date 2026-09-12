# Recipes

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Realistic problems worked end to end. Every one of these was executed against PostgreSQL 18 before it was published.

## Unique among live rows only

The classic soft-delete problem: `unique` on `email` stops a user from ever re-registering with an
address that was once deleted.

```php
Schema::create('users', static function (Blueprint $table) {
    $table->primaryUUID();
    $table->string('email');
    $table->softDeletes();

    $table->uniquePartial('email')->whereNull('deleted_at');
});
```

Two rows with the same address are now fine as long as at most one is live.

## One default per group

"Exactly one primary address per user", "one active price per product" — a unique index over the
grouping column, restricted to the rows that claim the role.

```php
Schema::create('addresses', static function (Blueprint $table) {
    $table->increments('id');
    $table->foreignId('user_id');
    $table->boolean('is_primary')->default(false);

    $table->uniquePartial('user_id', 'addresses_one_primary')->whereTrue('is_primary');
});
```

Any number of non-primary addresses, never two primary ones.

## At most one NULL

Nulls are distinct by default, so a plain unique index lets any number of them through.
`nullsNotDistinct()` reverses that — useful when `null` means something specific, such as "the
unassigned seat".

```php
Schema::create('memberships', static function (Blueprint $table) {
    $table->increments('id');
    $table->string('team');
    $table->string('seat')->nullable();
    $table->softDeletes();

    $table->uniquePartial(['team', 'seat'], 'memberships_seat_unique')
        ->nullsNotDistinct()
        ->whereNull('deleted_at');
});
```

*PostgreSQL >= 15.*

## Tag search over a text array

```php
Schema::create('posts', static function (Blueprint $table) {
    $table->increments('id');
    $table->textArray('tags');

    $table->ginIndex('tags');
});
```

```php
Post::whereRaw("tags @> ARRAY[?]::text[]", ['postgres'])->get();
```

## Containment queries on jsonb

```php
Schema::create('events', static function (Blueprint $table) {
    $table->primaryUUID();
    $table->jsonb('payload');

    $table->ginIndex('payload', 'events_payload_gin', 'jsonb_path_ops');
});
```

```php
Event::whereRaw("payload @> ?::jsonb", [json_encode(['type' => 'signup'])])->get();
```

`jsonb_path_ops` indexes only containment, which is what makes it smaller and faster than the
default. If you also need `?` key-existence operators, leave the operator class off.

## Fuzzy title search

```php
Schema::createExtensionIfNotExists('pg_trgm');

Schema::create('articles', static function (Blueprint $table) {
    $table->increments('id');
    $table->string('title');

    $table->ginIndex('title', 'articles_title_trgm', 'gin_trgm_ops');
});
```

```php
Article::where('title', 'ilike', '%postgres%')->get();   // now indexable
```

## Adding an index to a busy table

An ordinary `CREATE INDEX` holds a write lock for its duration. `online()` trades a second pass for
not blocking writes.

```php
return new class extends Migration {
    // CREATE INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::table('orders', static function (Blueprint $table) {
            $table->partial('customer_id', 'orders_open_customer')
                ->whereIn('state', ['new', 'paid'])
                ->online();
        });
    }
};
```

## A reporting view refreshed without downtime

```php
Schema::createView(
    'order_totals',
    'select customer_id, count(*) as orders, sum(total) as revenue from orders group by 1',
    true
);

// CONCURRENTLY needs a unique index on the view
DB::statement('create unique index order_totals_customer on order_totals (customer_id)');
```

```php
Schema::refreshMaterializedView('order_totals', true);
```

Readers keep seeing the previous contents while the refresh runs.

## Snapshot a table three ways

```php
// structure, indexes, defaults and constraints — no rows
Schema::create('orders_template', static function (Blueprint $table) {
    $table->like('orders')->includingAll();
});

// rows and columns — nothing else
Schema::create('orders_backup', static function (Blueprint $table) {
    $table->fromTable('orders');
});

// only what the query selects
Schema::create('orders_2026', static function (Blueprint $table) {
    $table->fromSelect("select * from orders where created_at >= '2026-01-01'");
});
```

## A sweep that reports what it touched

```php
$expired = DB::table('sessions')
    ->where('last_seen_at', '<', now()->subDays(30))
    ->deleteAndReturn('id', 'user_id');

foreach ($expired as $session) {
    SessionExpired::dispatch($session->user_id);
}
```

One statement, and the rows come back as they were — no select-then-delete race.

The same for a state transition that has to notify:

```php
$paid = Order::where('state', 'authorised')
    ->where('authorised_at', '<', now()->subMinutes(15))
    ->updateAndReturn(['state' => 'captured'], 'id', 'customer_id', 'total');
```

## UUID keys without an extension

```php
Schema::create('documents', static function (Blueprint $table) {
    $table->primaryUUID();                        // default gen_random_uuid()
    $table->generateUUID('parent_id', null);      // nullable reference
});

Schema::table('documents', static function (Blueprint $table) {
    $table->foreign('parent_id')->references('id')->on('documents');
});
```

`gen_random_uuid()` is built into PostgreSQL 13 and later — no `uuid-ossp`, no application-side
generation.

> The self-reference is added in a second step on purpose. `primaryUUID()` emits its primary key as
> a trailing `ALTER TABLE`, which Laravel orders *after* the foreign keys of the same blueprint, so
> declaring both together gives `there is no unique constraint matching given keys`. Splitting the
> statements avoids it; so does declaring `$table->primary('id')` explicitly before the
> `foreign()` call.

If your ids come from the application instead:

```php
$table->primaryUUID('id', false);   // uuid not null, no default
```

## Per-tenant views in their own schema

```php
foreach ($tenants as $tenant) {
    DB::statement("create schema if not exists {$tenant->schema}");

    Schema::createView(
        "{$tenant->schema}.active_users",
        "select id, email from users where tenant_id = '{$tenant->id}' and deleted_at is null"
    );
}

Schema::hasView("{$tenants[0]->schema}.active_users");   // true
```

## Dropping a table other objects depend on

```php
public function down(): void
{
    Schema::dropViewIfExists('order_totals', true);
    Schema::dropIfExistsCascade('orders');
}
```

`dropIfExistsCascade()` takes the dependents with it, which is what makes a `down()` reliable when
views were created over the table.

---
