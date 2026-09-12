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
