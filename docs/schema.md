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
