# laravel-support-db

![PHP >= 8.5](https://img.shields.io/badge/php->=8.5-blue.svg)
![Laravel >= 13.0](https://img.shields.io/badge/Laravel->=13.0-red.svg)
[![CI](https://github.com/efureev/laravel-support-db/actions/workflows/ci.yml/badge.svg)](https://github.com/efureev/laravel-support-db/actions/workflows/ci.yml)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/5c8b9e85897f4c65b5a017d16f6af6cb)](https://app.codacy.com/gh/efureev/laravel-support-db/dashboard)
[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-support-db/v/stable?format=flat)](https://packagist.org/packages/efureev/laravel-support-db)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-support-db/downloads)](https://packagist.org/packages/efureev/laravel-support-db)

PostgreSQL features for Laravel's schema and query builders.

Laravel's builders cover what every database can do. This package adds the PostgreSQL parts they
leave out — partial indexes, views, `CREATE TABLE … LIKE`, extensions, `RETURNING`, and the
PostgreSQL-only column types — using the same fluent style, so they read like the rest of a
migration.

It extends; it does not replace. Everything the framework already does keeps working unchanged.

```php
Schema::create('users', static function (Blueprint $table) {
    $table->primaryUUID();
    $table->string('email');
    $table->softDeletes();

    // one live user per address; soft-deleted rows do not collide
    $table->uniquePartial('email')->whereNull('deleted_at');
});
```

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- **Reference**
    - [Column types](#column-types)
    - [UUID keys](#uuid-keys)
    - [Column compression](#column-compression)
    - [Partial indexes](#partial-indexes)
    - [Index predicates](#index-predicates)
    - [Index modifiers](#index-modifiers)
    - [GIN indexes and operator classes](#gin-indexes-and-operator-classes)
    - [Views](#views)
    - [Creating a table from another](#creating-a-table-from-another)
    - [Dropping with CASCADE](#dropping-with-cascade)
    - [Extensions](#extensions)
    - [RETURNING on UPDATE and DELETE](#returning-on-update-and-delete)
- [Recipes](#recipes)
- [Behaviour notes](#behaviour-notes)
- [Already in Laravel 13](#already-in-laravel-13)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

## Requirements

| | Version | Notes |
|---|---|---|
| PHP | >= 8.5 | |
| Laravel | >= 13.0 | `illuminate/database` |
| PostgreSQL | 13 – 18 | every one of them is exercised in CI |

Two features need a newer server than the 13 floor, and only those two:

| Feature | Needs |
|---|---|
| `compression()` | PostgreSQL >= 14 |
| `nullsNotDistinct()` | PostgreSQL >= 15 |

The package targets PostgreSQL and takes effect on `pgsql` connections only. Other drivers on the
same application are untouched.

## Installation

```bash
composer require efureev/laravel-support-db
```

It registers itself through package discovery — no provider to add, no config to publish.

On boot it routes `pgsql` connections to its own connection class through
`Illuminate\Database\Connection::resolverFor()`. `DB::connection()` then returns
`Php\Support\Laravel\Database\Schema\Postgres\Connection`, `Schema::` reaches the extended builder,
and the closure in a `Schema::create()` receives the extended blueprint.

Type-hint the package's `Blueprint` in migrations to get the additions in your editor:

```php
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create('table', static function (Blueprint $table) { /* … */ });
```

A bundled `.meta.php` teaches IDEs about the additions on `Schema`, `Blueprint`, `ColumnDefinition`
and the query builder, so the framework's own type hints resolve to them too.

## Quick start

A migration using several of the additions at once:

```php
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', static function (Blueprint $table) {
            $table->primaryUUID();                       // uuid pk, gen_random_uuid()
            $table->generateUUID('tenant_id', null);     // uuid, nullable, no default
            $table->string('slug');
            $table->textArray('tags');                   // text[]
            $table->jsonb('payload');
            $table->tsRange('valid_for');                // tsrange
            $table->text('body')->compression('lz4');    // PostgreSQL >= 14
            $table->timestamps();
            $table->softDeletes();

            // unique per tenant, but only among rows that are still live
            $table->uniquePartial(['tenant_id', 'slug'])->whereNull('deleted_at');

            // containment queries on the jsonb column
            $table->ginIndex('payload', 'documents_payload_gin', 'jsonb_path_ops');

            // tag lookups
            $table->ginIndex('tags');
        });

        Schema::createView(
            'live_documents',
            'select id, tenant_id, slug from documents where deleted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropViewIfExists('live_documents');
        Schema::dropIfExistsCascade('documents');
    }
};
```

---

# Reference

## Column types

Each method returns a `ColumnDefinition`, so the framework's modifiers (`nullable()`, `default()`,
`index()`, `comment()`, …) chain off it as usual.

| Method | Column type | PostgreSQL docs |
|---|---|---|
| `bit(string $column, int $length)` | `bit(n)` | [Bit string](https://www.postgresql.org/docs/current/datatype-bit.html) |
| `numeric(string $column, ?int $precision = null, ?int $scale = null)` | `numeric`, `numeric(p)`, `numeric(p, s)` | [Numeric](https://www.postgresql.org/docs/current/datatype-numeric.html) |
| `dateRange(string $column)` | `daterange` | [Range types](https://www.postgresql.org/docs/current/rangetypes.html) |
| `tsRange(string $column)` | `tsrange` | [Range types](https://www.postgresql.org/docs/current/rangetypes.html) |
| `timestampRange(string $column)` | `tsrange` (alias of `tsRange`) | [Range types](https://www.postgresql.org/docs/current/rangetypes.html) |
| `ipNetwork(string $column)` | `cidr` | [Network types](https://www.postgresql.org/docs/current/datatype-net-types.html) |
| `geoPoint(string $column)` | `point` | [Geometric types](https://www.postgresql.org/docs/current/datatype-geometric.html) |
| `geoPath(string $column)` | `path` | [Geometric types](https://www.postgresql.org/docs/current/datatype-geometric.html) |
| `xml(string $column)` | `xml` | [XML](https://www.postgresql.org/docs/current/datatype-xml.html) |
| `uuidArray(string $column)` | `uuid[]` | [Arrays](https://www.postgresql.org/docs/current/arrays.html) |
| `textArray(string $column)` | `text[]` | [Arrays](https://www.postgresql.org/docs/current/arrays.html) |
| `intArray(string $column)` | `integer[]` | [Arrays](https://www.postgresql.org/docs/current/arrays.html) |

`numeric()` differs from the framework's `decimal()` in that both arguments are optional — omit
them for an unconstrained `numeric`, which stores any precision:

```php
$table->numeric('amount');          // numeric
$table->numeric('amount', 10);      // numeric(10)
$table->numeric('amount', 10, 2);   // numeric(10, 2)
```

> `information_schema` normalises some of these on the way back: all three array types report as
> `ARRAY`, and `numeric(10)` reads as `numeric(10,0)`. That is PostgreSQL, not the package — the
> emitted DDL is exactly what the table above says.

## UUID keys

`primaryUUID()` is a UUID column plus its primary key; `generateUUID()` is the column alone. Both
default to the native `gen_random_uuid()`, which needs no extension on PostgreSQL 13 and later.

```php
$table->primaryUUID();                // "id" uuid not null default gen_random_uuid() + pk
$table->primaryUUID('uid');           // same, named "uid"
$table->primaryUUID('id', false);     // pk you populate yourself
```

The second argument decides where the value comes from, and `primaryUUID()` passes it straight
through to `generateUUID()`:

| Argument | Result |
|---|---|
| `true` *(default)* | `uuid not null default gen_random_uuid()` |
| `false` | `uuid not null` — no default, you must supply a value |
| `null` | `uuid null` — nullable, no default |
| `Expression` | `uuid not null default <expression>` |
| `callable(string $column): string` | `uuid not null default <the string it returns>` |

```php
use Illuminate\Database\Query\Expression;

$table->generateUUID();                                  // "id", database-generated
$table->generateUUID('cid');                             // "cid", database-generated
$table->generateUUID('tenant_id', null)->index();        // nullable FK column, indexed
$table->generateUUID('external_id', false);              // not null, supplied by the application
$table->generateUUID('id', new Expression('uuid_generate_v4()'));
$table->generateUUID('id', fn (string $c) => "uuid_generate_v5(uuid_ns_url(), '$c')");
```

> The `uuid_generate_*` family comes from `uuid-ossp` — call
> `Schema::createExtensionIfNotExists('uuid-ossp')` first. The default `gen_random_uuid()` is
> built in and needs nothing.

## Column compression

PostgreSQL 14 and later can compress TOAST-able columns with either `pglz` (the historical
algorithm) or `lz4` (faster, usually a better ratio).
[Docs](https://www.postgresql.org/docs/current/storage-toast.html).

```php
$table->text('body')->compression('lz4');      // text compression lz4
$table->text('body')->compression();           // defaults to pglz
$table->text('body')->compression('default');  // the server's default_toast_compression
```

It works on `change()` too, where PostgreSQL takes it as a separate statement:

```php
$table->text('body')->compression('lz4')->change();
// alter table "docs" alter column "body" set compression lz4
```

Only the new rows are compressed with the new method; existing ones keep whatever they were
written with until they are rewritten.

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

## Views

```php
Schema::createView('active_users', 'select id, email from users where is_active');
Schema::createViewOrReplace('active_users', 'select id, email, role from users where is_active');

// third argument makes it MATERIALIZED
Schema::createView('order_totals', 'select user_id, sum(total) from orders group by 1', true);
```

The same methods exist on the blueprint, if you would rather create a view alongside its table:

```php
Schema::table('users', static function (Blueprint $table) {
    $table->createView('active_users', 'select id from users where is_active');
});
```

**Refreshing a materialized view.**

```php
Schema::refreshMaterializedView('order_totals');
Schema::refreshMaterializedView('order_totals', true);   // CONCURRENTLY
```

> `CONCURRENTLY` keeps the view readable while it rebuilds, but PostgreSQL requires the view to
> carry a unique index and to have been populated at least once. It is also refused inside a
> transaction block.

**Inspecting.** Both of these see materialized views, which the framework's own `hasView()` does
not — it reads `pg_views`, where materialized views do not appear.

```php
Schema::hasView('active_users');            // bool
Schema::getViewDefinition('active_users');  // the SELECT, or '' if there is no such view
```

**Other schemas.** Every view method takes a `schema.view` reference, the lookups included:

```php
Schema::createView('reporting.active_users', 'select id from users where is_active');

Schema::hasView('reporting.active_users');           // true
Schema::hasView('active_users');                     // false — a different view
Schema::getViewDefinition('reporting.active_users');
Schema::dropView('reporting.active_users');
```

Without a schema the connection's own is used. A three-part reference is rejected.

**Dropping.** A materialized view must be dropped as such — `DROP VIEW` fails on one:

```php
Schema::dropView('active_users');
Schema::dropViewIfExists('active_users');

Schema::dropView('order_totals', true);          // drop materialized view
Schema::dropViewIfExists('order_totals', true);
```

## Creating a table from another

Three PostgreSQL forms, three different things copied.

**`like()` — structure, no data.** The closest thing to a template.

```php
Schema::create('users_archive', static function (Blueprint $table) {
    $table->like('users')->includingAll();
    $table->ifNotExists();
});
// create table if not exists "users_archive" (like "users" including all)
```

`includingAll()` is PostgreSQL's shorthand for `INCLUDING DEFAULTS, CONSTRAINTS, INDEXES, STORAGE,
COMMENTS`. Drop it to copy the column definitions alone.

**`fromTable()` — columns and data, nothing else.** No indexes, no constraints, no defaults.

```php
Schema::create('users_snapshot', static function (Blueprint $table) {
    $table->fromTable('users');
});
// create table "users_snapshot" as table "users"
```

**`fromSelect()` — whatever the query returns.**

```php
Schema::create('active_snapshot', static function (Blueprint $table) {
    $table->fromSelect('select id, email from users where is_active');
});
// create table "active_snapshot" as (select id, email from users where is_active)
```

The query is arbitrary, so this is also how you reshape on the way:

```php
Schema::create('users_reindexed', static function (Blueprint $table) {
    $table->fromSelect('select gen_random_uuid() as id, email, created_at from users');
});
```

**`ifNotExists()`** adds `IF NOT EXISTS` to any `Schema::create()`, with or without the above.

## Dropping with CASCADE

Drops the table and everything depending on it — views, foreign keys, sequences, and whatever
depends on those in turn.

```php
Schema::dropIfExistsCascade('users');
```

Without it, PostgreSQL refuses to drop a table a view is built on. Useful in `down()` and in test
teardown; deliberate everywhere else, since the blast radius is by definition not local.

## Extensions

```php
Schema::createExtension('uuid-ossp');             // fails if it is already there
Schema::createExtensionIfNotExists('uuid-ossp');  // idempotent

Schema::dropExtensionIfExists('tablefunc');
Schema::dropExtensionIfExists('tablefunc', 'fuzzystrmatch');   // several at once
```

Creating an extension usually needs a superuser or an explicitly trusted extension.

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

# Recipes

Realistic problems and the shape of their solution. Every one of these runs — they are executed
against PostgreSQL as part of preparing this document.

### Unique among live rows only

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

### One default per group

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

### At most one NULL

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

### Tag search over a text array

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

### Containment queries on jsonb

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

### Fuzzy title search

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

### Adding an index to a busy table

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

### A reporting view refreshed without downtime

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

### Snapshot a table three ways

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

### A sweep that reports what it touched

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

### UUID keys without an extension

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

### Per-tenant views in their own schema

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

### Dropping a table other objects depend on

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

# Behaviour notes

Things that are easy to trip over, gathered in one place.

**Server version floors.** `compression()` needs PostgreSQL 14, `nullsNotDistinct()` needs 15.
Everything else works from 13. All six versions run in CI.

**`CONCURRENTLY` cannot run in a transaction.** That applies to `online()` on an index and to
`refreshMaterializedView($view, true)`. Laravel does not wrap migrations in a transaction by
default; if yours does, set `$withinTransaction = false` on it.

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

# Already in Laravel 13

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

# Testing

The package targets PostgreSQL, so the functional suite needs a running server.

**With Docker** — nothing local required:

```bash
composer test:docker

# both versions are overridable
POSTGRES_VERSION=15 composer test:docker
PHP_VERSION=8.5 composer test:docker
```

**Locally** — connection settings come from the environment, defaulting to `forge`/`forge`/`forge`
on `localhost:5432`:

```bash
composer test         # PHPCS + the whole suite
composer test-cover   # with coverage
composer phpunit-unit # the unit suite alone — asserts on generated SQL, needs no database
```

# Contributing

Issues and pull requests are welcome. Before opening one, run the gate CI runs:

```bash
composer phpcs      # PSR-12 over src and tests
composer phpstan    # level 6 with larastan
composer test       # PHPCS + the whole suite, needs PostgreSQL
```

New behaviour wants a test that fails without it — the suite is checked by mutation, not by
coverage percentage.

# License

MIT — see [LICENSE](LICENSE).
