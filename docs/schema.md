# Schema operations

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Creating a table from another, dropping with dependents, and managing extensions.

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

```sql
drop table if exists "users" cascade
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

```sql
create extension "uuid-ossp"
create extension if not exists "uuid-ossp"

drop extension if exists "tablefunc"
drop extension if exists "tablefunc", "fuzzystrmatch"
```

Creating an extension usually needs a superuser or an explicitly trusted extension.

## Types of your own

Laravel can read a user-defined type back and drop every one at once, but has no way to create a
single one.

**Enums.** A label set the database itself enforces, ordered the way you declare it:

```php
Schema::createEnumType('order_state', ['new', 'paid', 'shipped']);
```

```sql
create type "order_state" as enum ('new', 'paid', 'shipped')
```

The type is then an ordinary column type — `$table->rawColumn('state', 'order_state')` — and
PostgreSQL refuses any label it does not know.

Labels are added to an existing type, optionally positioned. The order matters: comparisons and
`ORDER BY` follow it, not the alphabet.

```php
Schema::addEnumValue('order_state', 'refunded');            // at the end
Schema::addEnumValue('order_state', 'pending', 'new');      // before 'new'
Schema::addEnumValue('order_state', 'in_transit', after: 'paid');
```

> PostgreSQL 12 and later allow this inside a transaction, so an ordinary migration can do it — as
> long as the new label is not also *used* in that same transaction.

**Domains.** A base type with a rule attached, so the rule lives with the type instead of being
repeated on every column that uses it:

```php
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;

Schema::createDomain('positive_int', 'integer', fn (PartialBuilder $c) => $c->where('value', '>', 0));
Schema::createDomain('score', 'integer', fn (PartialBuilder $c) => $c->where('value', '>=', 0)->where('value', '<=', 100));
```

```sql
create domain "positive_int" as integer check (("value" > 0))
```

The predicate names `value` — PostgreSQL calls the thing being checked `VALUE`, and resolves a
quoted `"value"` to it, so the vocabulary from [Index predicates](indexes.md) reaches here
unchanged. The catalogue stores it as `CHECK ((VALUE > 0))`.

**Composites.** Several fields under one name:

```php
Schema::createCompositeType('full_name', ['first' => 'text', 'last' => 'varchar(30)']);
```

```sql
create type "full_name" as ("first" text, "last" varchar(30))
```

**Dropping.** Types and domains are dropped separately, several at a time, and a name may be
schema-qualified:

```php
Schema::dropTypeIfExists('order_state', 'full_name');
Schema::dropDomainIfExists('positive_int');
Schema::dropTypeIfExistsCascade('order_state');   // and every column using it
```

> Values and base type names are interpolated into DDL — PostgreSQL takes no parameter there — so
> a value is escaped and a type name is checked against what a type name may look like.

## Exclusion constraints

A unique index says two rows must not be equal. An exclusion constraint says they must not
*overlap*, intersect, or whatever else an operator expresses — which is what a range column exists
for, and which Laravel has no form for:

```php
Schema::createExtensionIfNotExists('btree_gist');

Schema::create('bookings', static function (Blueprint $table) {
    $table->increments('id');
    $table->integer('room_id');
    $table->tsRange('during');
    $table->timestamp('cancelled_at')->nullable();

    $table->exclusion('bookings_no_overlap')
        ->using('gist')
        ->with('room_id', '=')
        ->with('during', '&&')
        ->whereNull('cancelled_at');
});
```

```sql
alter table "bookings" add constraint "bookings_no_overlap"
    exclude using gist ("room_id" with =, "during" with &&)
    where ("cancelled_at" is null)
```

No two live bookings for the same room may overlap; a cancelled one is outside the constraint and
may overlap anything. `with()` accumulates, and the predicate takes the whole vocabulary from
[Index predicates](indexes.md).

> **A range operator needs `gist`.** The default access method is btree, which supports only `=`;
> `&&` against it fails with *operator &&(anyrange,anyrange) is not a member of operator family*.
> Mixing a scalar column into the same constraint additionally needs `btree_gist`, since gist alone
> cannot index an integer.

The operator is checked against the character set PostgreSQL builds operator names from, because
it is interpolated into DDL and cannot be bound.

Dropping works for any named constraint:

```php
$table->dropConstraint('bookings_no_overlap');
$table->dropCheck('price_positive');          // the same statement, named for what it drops
```

## Check constraints

Laravel's schema builder has no `CHECK` in any grammar, so a table's columns can be described and
most of its invariants cannot. The condition is written with the same vocabulary as a partial
index — PostgreSQL treats both as a boolean expression over a row:

```php
Schema::create('products', static function (Blueprint $table) {
    $table->integer('price');
    $table->string('state');

    $table->check('price_positive')->where('price', '>', 0);
    $table->check('state_known')->whereIn('state', ['new', 'paid', 'shipped']);
});
```

```sql
alter table "products" add constraint "price_positive" check (("price" > 0))
alter table "products" add constraint "state_known" check (("state" in ('new','paid','shipped')))
```

Each is emitted as its own `ALTER TABLE`, so the same call works on a table being created and on
one that already exists. Dropping takes the constraint name:

```php
Schema::table('products', static fn (Blueprint $table) => $table->dropCheck('price_positive'));
```

```sql
alter table "products" drop constraint "price_positive"
```

Every predicate from [Index predicates](indexes.md) is available. A check with no conditions is
refused rather than compiled — `check (())` is a syntax error.

## Partitioning

A partitioned table holds no rows; its partitions do, and PostgreSQL routes every insert to the
right one. Laravel has no form for any of it.

```php
Schema::create('events', static function (Blueprint $table) {
    $table->bigInteger('id');
    $table->timestamp('at');
    $table->primary(['id', 'at']);

    $table->partitionBy('range', 'at');      // or 'list', or 'hash'
});
```

```sql
create table "events" ("id" bigint not null, "at" timestamp(0) without time zone not null,
    primary key ("id", "at")) partition by range ("at")
```

> **The primary key must contain every partitioning column.** PostgreSQL requires it and says so
> obscurely — *unique constraint on partitioned table must include all partitioning columns*. The
> pair that trips it is `bigIncrements('id')` beside `partitionBy('range', 'at')`, which looks
> perfectly ordinary. This package checks first and names the actual problem, so partition by a
> column the key already has, or widen the key as above.

A partition takes no column list of its own — it inherits the parent's:

```php
Schema::create('events_2026', fn (Blueprint $t) => $t->partitionOf('events')->fromTo('2026-01-01', '2027-01-01'));
Schema::create('events_rest', fn (Blueprint $t) => $t->partitionOf('events')->asDefault());

Schema::create('logs_eu',   fn (Blueprint $t) => $t->partitionOf('logs')->in(['de', 'fr']));
Schema::create('shards_0',  fn (Blueprint $t) => $t->partitionOf('shards')->hash(modulus: 4, remainder: 0));
```

```sql
create table "events_2026" partition of "events" for values from ('2026-01-01') to ('2027-01-01')
create table "events_rest" partition of "events" default
create table "logs_eu" partition of "logs" for values in ('de', 'fr')
create table "shards_0" partition of "shards" for values with (modulus 4, remainder 0)
```

An existing table can be adopted, and a partition released back into one of its own — the rows go
with it:

```php
Schema::table('events', static function (Blueprint $table) {
    $table->attachPartition('events_2025')->fromTo('2025-01-01', '2026-01-01');
    $table->detachPartition('events_2024');
    $table->detachPartition('events_2023', concurrently: true);
});
```

> `detachPartition(concurrently: true)` avoids the access-exclusive lock and, like every
> `CONCURRENTLY` in PostgreSQL, cannot run inside a transaction block — see
> [Behaviour notes](behaviour.md).

## Row-level security

Per-row authorisation the database enforces itself, which no amount of application code can be
talked out of. It pairs with the schema-qualified views above for multi-tenant data.

```php
Schema::create('documents', static function (Blueprint $table) {
    $table->increments('id');
    $table->string('tenant');
    $table->text('body');

    $table->enableRowLevelSecurity();
    $table->forceRowLevelSecurity();        // the owner obeys the policies too

    $table->policy('tenant_read')
        ->for('select')
        ->to('app_user')
        ->using(fn (PartialBuilder $w) => $w->whereRaw('tenant = current_setting(?, true)', ['app.tenant']));

    $table->policy('tenant_write')
        ->for('insert')
        ->to('app_user')
        ->withCheck(fn (PartialBuilder $w) => $w->whereRaw('tenant = current_setting(?, true)', ['app.tenant']));
});
```

```sql
alter table "documents" enable row level security
alter table "documents" force row level security
create policy "tenant_read" on "documents" for select to "app_user"
    using ((tenant = current_setting('app.tenant', true)))
create policy "tenant_write" on "documents" for insert to "app_user"
    with check ((tenant = current_setting('app.tenant', true)))
```

`using` decides which rows a statement may see; `withCheck` decides which it may leave behind.
Both take the predicate vocabulary from [Index predicates](indexes.md), and a policy with neither
is refused — it would permit nothing, which is a mistake more often than an intention.

```php
$table->dropPolicy('tenant_read');
$table->disableRowLevelSecurity();
```

> Switching security on hides every row until a policy permits one. Without `force`, the table
> owner bypasses the policies entirely — which is easy to miss when testing as the owner.

## How a table is stored

```php
Schema::create('cache', static function (Blueprint $table) {
    $table->string('key');

    $table->unlogged();                                    // no write-ahead log
    $table->storageParameters(['fillfactor' => 70, 'autovacuum_vacuum_scale_factor' => 0.05]);
});

Schema::table('cache', fn (Blueprint $t) => $t->resetStorageParameters('fillfactor'));
```

```sql
create unlogged table "cache" ("key" varchar(255) not null)
alter table "cache" set (fillfactor = 70, autovacuum_vacuum_scale_factor = 0.05)
alter table "cache" reset (fillfactor)
```

> An unlogged table is faster to write and is **emptied after a crash** and never replicated. For
> data you can rebuild, and nothing else. `temporary()` and `unlogged()` are mutually exclusive;
> temporary wins.

Storage parameters are their own `ALTER TABLE`, so the same call works on a new table and on one
that already exists. Both the name and the value are checked, since neither can be a bound
parameter.

## Extended statistics

The planner assumes columns are independent. When they are not — a city that implies its country,
a status that implies its type — it multiplies the two selectivities and lands orders of magnitude
off, and an estimate that wrong usually picks the wrong plan.

```php
Schema::create('events', static function (Blueprint $table) {
    $table->string('kind');
    $table->string('region');

    $table->statistics('events_kind_region')->on('kind', 'region');
});
```

```sql
create statistics "events_kind_region" on "kind", "region" from "events"
```

That is measurable rather than theoretical. Over a table where `kind` and `region` agree exactly,
`explain` estimates about a ninth of the rows before the statistics exist and about a third after
— which is the correct answer, and the package's own test asserts the improvement.

| Kind | What it records |
|---|---|
| `ndistinct` | how many distinct combinations the columns have together |
| `dependencies` | that one column's value implies another's |
| `mcv` | the commonest combinations, with their frequencies |

All three are collected unless you name fewer:

```php
$table->statistics('s')->on('kind', 'region')->kinds('ndistinct', 'dependencies');
$table->statistics('s')->ifNotExists()->on('kind', 'region');
$table->dropStatistics('s', 'other');
```

> **At least two columns.** PostgreSQL refuses one, because a single column's distribution is what
> it already gathers by itself — the package says so before the statement is sent.
>
> An `Expression` may stand in for a column, which needs PostgreSQL 14; before that the server
> accepts only plain column references.

Statistics are gathered by `ANALYZE`, so a fresh object tells the planner nothing until the next
analyze — automatic or otherwise.
