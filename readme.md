# PHP Laravel Database Support

![PHP >= 8.5](https://img.shields.io/badge/php->=8.5-blue.svg)
![Laravel >= 13.0](https://img.shields.io/badge/Laravel->=13.0-red.svg)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/5c8b9e85897f4c65b5a017d16f6af6cb)](https://app.codacy.com/gh/efureev/laravel-support-db/dashboard)
[![CI](https://github.com/efureev/laravel-support-db/actions/workflows/ci.yml/badge.svg)](https://github.com/efureev/laravel-support-db/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-support-db/v/stable?format=flat)](https://packagist.org/packages/efureev/laravel-support-db)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-support-db/downloads)](https://packagist.org/packages/efureev/laravel-support-db)

## Description

Laravel's schema builder covers what every database can do. This package adds the PostgreSQL
parts it leaves out: partial and partial-unique indexes, views (including materialized ones),
`CREATE TABLE ... LIKE / AS SELECT / AS TABLE`, extensions, column compression, `RETURNING` on
`UPDATE` and `DELETE`, and shorthands for PostgreSQL-only column types.

It is an extension, not a replacement — everything the framework already does keeps working
exactly as before.

## Requirements

| | Version | Notes |
|---|---|---|
| PHP | >= 8.5 | |
| Laravel | >= 13.0 | `illuminate/database` |
| PostgreSQL | 13 – 18 | tested against every one of them in CI |
| | >= 14 | only for `compression()`; on 13 the feature is unavailable |

The package targets PostgreSQL and takes effect on `pgsql` connections only.

## Install

```bash
composer require efureev/laravel-support-db
```

It registers itself through package discovery. On boot it points `pgsql` connections at its own
connection class via `Illuminate\Database\Connection::resolverFor()`, so `DB::connection()`
returns `Php\Support\Laravel\Database\Schema\Postgres\Connection` and `Schema::` reaches the
extended builder. Worth knowing if another package resolves the same driver — the last one
registered wins.

## Contents

- [Ext Column Types](#ext-column-types)
    - [Bit](#bit)
    - [Numeric](#numeric)
    - [GeoPoint](#geo-point)
    - [GeoPath](#geo-path)
    - [IP Network](#ip-network)
    - [Ranges](#ranges)
    - [UUID](#uuid)
    - [XML](#xml)
    - [Array of UUID](#array-of-uuid)
    - [Array of Integer](#array-of-integer)
    - [Array of Text](#array-of-text)
- [Column Options](#column-options)
    - [Compression](#compression)
- [Views](#views)
    - [Create views](#create-views)
    - [Refreshing materialized views](#refreshing-materialized-views)
    - [Dropping views](#dropping-views)
    - [Views in another schema](#views-in-another-schema)
- [Indexes](#indexes)
    - [Partial indexes](#partial-indexes)
    - [GIN indexes](#gin-indexes)
    - [Unique Partial indexes](#unique-partial-indexes)
- [Extended Schema](#extended-schema)
    - [Create like another table](#create-like-another-table)
    - [Create as another table with full data](#create-as-another-table-with-full-data)
    - [Create as another table with data from select query](#create-as-another-table-with-data-from-select-query)
    - [Drop Cascade If Exists](#drop-cascade-if-exists)
- [Extended Query Builder](#extended-query-builder)
    - [Update records and return updated records' columns](#update-records-and-return-updated-records-columns)
    - [Delete records and return deleted records' columns](#delete-records-and-return-deleted-records-columns)
- [Extensions](#extensions)
    - [Create Extensions](#create-extensions)
    - [Dropping Extensions](#dropping-extensions)
- [Already in Laravel 13](#already-in-laravel-13)

### Ext Column Types

#### Bit

Bit String.
[Doc](https://www.postgresql.org/docs/current/datatype-bit.html).

```php
$table->bit(string $column, int $length);
```

#### Geo Point

Points are the fundamental two-dimensional building block for geometric types.
[Doc](https://www.postgresql.org/docs/current/datatype-geometric.html#id-1.5.7.16.5).

```php
$table->geoPoint(string $column);
```

#### Geo Path

Paths are represented by lists of connected points.
[Doc](https://www.postgresql.org/docs/current/datatype-geometric.html#id-1.5.7.16.9).

```php
$table->geoPath(string $column);
```

#### IP Network

The IP network datatype stores an IP network in CIDR notation.
[Doc](https://www.postgresql.org/docs/current/datatype-net-types.html).

IPv4 = 7 bytes
IPv6 = 19 bytes

```php
$table->ipNetwork(string $column);
```

#### Ranges

The range data types store a range of values with optional start and end values. They can be used e.g. to describe the
duration a meeting room is booked.
[Doc](https://www.postgresql.org/docs/current/rangetypes.html).

```php
$table->dateRange(string $column);
$table->tsRange(string $column);
$table->timestampRange(string $column);
```

#### Numeric

Like `decimal`, but the precision and the scale are both optional — omit them for an unconstrained
`numeric`.
[Doc](https://www.postgresql.org/docs/current/datatype-numeric.html).

```php
$table->numeric('amount');          // numeric
$table->numeric('amount', 10);      // numeric(10)
$table->numeric('amount', 10, 2);   // numeric(10, 2)
```

#### UUID

The `primaryUUID` can be used to store UUID-type as primary key.

```php
$table->primaryUUID();                 // PK UUID column named `id`
$table->primaryUUID('custom_name');    // PK UUID column named `custom_name`
$table->primaryUUID('id', false);      // no generated default — you supply the value
```

The second argument is passed straight to `generateUUID()` below, so it accepts the same values.

The `generateUUID` can be used to store UUID-type with/without index (or FK).

On a row creating generates a value with the native `gen_random_uuid()` function (PostgreSQL >= 13, no extension required).

```php
use Illuminate\Database\Query\Expression;

// `id`, generated by the database with gen_random_uuid().
$table->generateUUID();

// `cid`, generated by the database.
$table->generateUUID('cid');

// `id`, nullable, no generated value — default NULL.
$table->generateUUID('id', null);

// `fk_id`, nullable with no generated value, plus an index.
$table->generateUUID('fk_id', null)->index();

// `fk_id`, not null and with no generated value — you supply it.
$table->generateUUID('fk_id', false);

// `fk_id`, generated by an expression you build from the column name.
$table->generateUUID('fk_id', fn(string $column) => "uuid_generate_v5(uuid_ns_url(), '$column')");

// `fk_id`, generated by an expression you pass verbatim.
$table->generateUUID('fk_id', new Expression('uuid_generate_v4()'));
```

> The last two use `uuid-ossp` functions, which need the extension:
> `Schema::createExtensionIfNotExists('uuid-ossp')`. The default `gen_random_uuid()` does not.

#### XML

The xml data type can be used to store an XML document.
[Doc](https://www.postgresql.org/docs/current/datatype-xml.html).

```php
$table->xml(string $column);
```

#### Array of UUID

The array of UUID data type can be used to store an array of IDs (uuid type).

```php
$table->uuidArray(string $column);
```

#### Array of Integer

The array of integer data type can be used to store a list of integers.

```php
$table->intArray(string $column);
```

#### Array of Text

The array of text data type can be used to store a list of strings.

```php
$table->textArray(string $column);
```

### Column Options

#### Compression

PostgreSQL 14 introduced the possibility to specify the compression method for toast-able data types. You can choose
between the default method `pglz`, the `lz4` algorithm and the value `default` to use the server default
setting.
[Doc](https://www.postgresql.org/docs/current/storage-toast.html).

```php
$table->string('col')->compression('lz4');
```

### Views

#### Create views

```php
// Facade methods:
Schema::createView('active_users', "SELECT * FROM users WHERE active = 1");
Schema::createViewOrReplace('active_users', "SELECT * FROM users WHERE active = 1");

// Pass `true` as the third argument for a MATERIALIZED view:
Schema::createView('active_users', "SELECT * FROM users WHERE active = 1", true);

// Schema methods:
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::table('users', function (Blueprint $table) {
    $table->createView('active_users', "SELECT * FROM users WHERE active = 1");
});
```

> PostgreSQL has no `CREATE OR REPLACE` for materialized views, so
> `createViewOrReplace(..., true)` throws a `LogicException`. Drop and recreate the view instead.

#### Refreshing materialized views

```php
Schema::refreshMaterializedView('active_users');

// CONCURRENTLY needs a unique index on the view and a first population:
Schema::refreshMaterializedView('active_users', true);
```

#### Dropping views

```php
Schema::dropView('active_users');
Schema::dropViewIfExists('active_users');

// Materialized views must be dropped as such — `DROP VIEW` fails on them:
Schema::dropView('active_users', true);
Schema::dropViewIfExists('active_users', true);
```

#### Views in another schema

Every view method takes a `schema.view` reference, the lookups included:

```php
Schema::createView('reporting.active_users', "SELECT * FROM users WHERE active = 1");

Schema::hasView('reporting.active_users');           // true
Schema::hasView('active_users');                     // false — a different view
Schema::getViewDefinition('reporting.active_users');
Schema::dropView('reporting.active_users');
```

Without a schema the connection's own is used. Identifiers are quoted rather than folded, so
`createView('MyView', ...)` really does make a view named `MyView`, and that is the name to ask
for later.

### Indexes

#### Partial indexes

See the [PostgreSQL docs on partial indexes](https://www.postgresql.org/docs/current/indexes-partial.html).

Example:

```php
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
Schema::create('table', static function (Blueprint $table) {
    $table->string('code');
    $table->softDeletes();
    $table
        ->partial('code')
        ->whereNull('deleted_at');
});
```

Pass an index method as the third argument (or chain `->algorithm()`) to pick the access method.
PostgreSQL places it as `USING <method>`:

```php
Schema::create('table', static function (Blueprint $table) {
    $table->textArray('tags');
    $table->softDeletes();

    $table->partial('tags', null, 'gin')->whereNull('deleted_at');
    // identical:
    $table->partial('tags')->algorithm('gin')->whereNull('deleted_at');
});
```

> The same argument works on `uniquePartial()`, but note that PostgreSQL only supports `UNIQUE`
> for `btree` — any other access method is rejected by the server.

If you want to delete partial index, use this method:

```php
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create('table', static function (Blueprint $table) {
    $table->dropPartial(['code']);
});
```

#### GIN indexes

A shortcut for `$table->index($columns, $name, 'gin')`, handy for the array and `jsonb` columns:

```php
Schema::create('table', static function (Blueprint $table) {
    $table->textArray('tags');
    $table->ginIndex('tags');
});
```

A third argument names an [operator class](https://www.postgresql.org/docs/current/indexes-opclass.html),
which is where GIN indexes earn their keep — `jsonb_path_ops` builds a smaller, faster index for
containment (`@>`) queries, and `gin_trgm_ops` (from the `pg_trgm` extension) makes `LIKE '%...%'`
and similarity search indexable:

```php
Schema::create('table', static function (Blueprint $table) {
    $table->jsonb('payload');
    $table->ginIndex('payload', 'table_payload_gin', 'jsonb_path_ops');
});
```

```SQL
CREATE INDEX table_payload_gin ON "table" USING gin ("payload" jsonb_path_ops)
```

The framework accepts an operator class on spatial and vector indexes only; its `index()` takes no
such argument, and `compileIndex()` drops one if it somehow arrives.

#### Unique Partial indexes

Example:

```php
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
Schema::create('table', static function (Blueprint $table) {
    $table->string('code');
    $table->softDeletes();
    $table
        ->uniquePartial('code')
        ->whereNull('deleted_at');
});
```

If you want to delete partial unique index, use this method:

```php
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create('table', static function (Blueprint $table) {
    $table->dropUniquePartial(['code']);
});
```

`$table->dropUnique()` doesn't work for Partial Unique Indexes, because PostgreSQL doesn't define a partial (ie
conditional) UNIQUE constraint. If you try to delete such a Partial Unique Index you will get an error.

```SQL
CREATE UNIQUE INDEX CONCURRENTLY examples_new_col_idx ON examples (new_col);
ALTER TABLE examples
    ADD CONSTRAINT examples_unique_constraint USING INDEX examples_new_col_idx;
```

When you create a unique index without conditions, PostgreSQL will create Unique Constraint automatically for you, and
when you try to delete such an index, Constraint will be deleted first, then Unique Index.

### Extended Schema

#### Create like another table

Create a table from a source-table. Creates a structure only.
`includingAll` copies all dependencies from source-table.

The table is created without data.

```php
Schema::create('target_table', function (Blueprint $table) {
    $table->like('source_table')->includingAll();
    $table->ifNotExists();
});
```

#### Create as another table with full data

Copy a table from a source-table. Copies only the columns and the data. Without indexes and so on...

```php
Schema::create('target_table', function (Blueprint $table) {
    $table->fromTable('source_table');
});
```

#### Create as another table with data from select query

Create a table from a select query. Copy only columns and a data. Without indexes and so on...

```php
Schema::create('target_table', function (Blueprint $table) {
    $table->fromSelect('select id, name from source_table');
});

// or

Schema::create('target_table', function (Blueprint $table) {
    $table->fromSelect(
        'select t1.id, t2.enabled, t2.extra from source_table t1 ' .
        'join source_table_2 t2 on t1.id = t2.src_id ' .
        'where t2.enabled = true'
    );
});

// The two examples below need this source table first:

$tbl = 'source_table';
Schema::create(
    $tbl,
    static function (Blueprint $table) {
        $table->string('key', 16)->primary();
        $table->string('title');
        $table->integer('sort')->index();
    }
);

// or

Schema::create('target_table', function (Blueprint $table) use ($tbl) {
    $table->fromSelect(
        'select gen_random_uuid() as id, key, title, sort from ' . $tbl
    );
});

// or

Schema::create('target_table', function (Blueprint $table) use ($tbl) {
    $table->fromSelect(
        'select gen_random_uuid() as id, * from ' . $tbl
    );
});
```

#### Drop Cascade If Exists

Automatically drop objects that depend on the table (such as views, indexes, seqs), and in turn all objects that depend
on those objects.

```php
Schema::dropIfExistsCascade('table');
```

### Extended Query Builder

#### Update records and return updated records' columns

```php
$list = Model::toBase()->updateAndReturn(['deleted_at' => now()], 'id', 'name');
```

```php
$list = Model::where(['enabled' => true])->updateAndReturn(['enabled' => false], 'id');
```

> The two forms differ: through Eloquent the model's `updated_at` is maintained as usual, while
> `toBase()` drops to the query builder and writes only the columns you pass.

Rows come back in the connection's configured fetch mode, the same as `DB::select()` — `stdClass`
objects unless you have changed it.

#### Delete records and return deleted records' columns

```php
$list = Model::toBase()->deleteAndReturn('id', 'name');
```

```php
$list = Model::where(['enabled' => true])->deleteAndReturn('id');
```

### Extensions

#### Create Extensions

The Schema facade supports the creation of extensions with the `createExtension` and `createExtensionIfNotExists`
methods:

```php
Schema::createExtension('uuid-ossp');
Schema::createExtensionIfNotExists('uuid-ossp');
```

#### Dropping Extensions

To remove extensions, you may use the `dropExtensionIfExists` methods provided by the Schema facade:

```php
Schema::dropExtensionIfExists('tablefunc');
```

You may drop many extensions at once by passing multiple extension names:

```php
Schema::dropExtensionIfExists('tablefunc', 'fuzzystrmatch');
```

---

## Usage

### Simple example

```php
<?php

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create(
    'test_table',
    static function (Blueprint $table) {
        $table->primaryUUID();
        $table->generateUUID('id', null);
        $table->tsRange('range');
        $table->numeric('num');

    }
);
```

## Test

The package targets **PostgreSQL** only, so a running PostgreSQL instance is required.

### With Docker (recommended)

A `docker-compose.yml` ships a disposable PostgreSQL instance and a PHP runner, so no local
PHP or PostgreSQL is needed:

```bash
composer test:docker
# equivalent to:
# docker compose up --build --abort-on-container-exit --exit-code-from app

# Both versions are overridable:
POSTGRES_VERSION=15 composer test:docker
PHP_VERSION=8.5 composer test:docker
```

### Locally

Provide DB connection settings via environment variables (defaults: `forge` / `forge` / `forge` on
`localhost:5432`), then run:

```bash
composer test        # PHPCS + PHPUnit
composer test-cover  # with coverage (pcov)
```

## Already in Laravel 13

Some of what this package used to be needed for now ships with the framework. Reach for these
first — they are not duplicated here:

| Feature | Native form |
|---|---|
| Partial-free unique index options | `$table->unique($cols)->nullsNotDistinct()->deferrable()->initiallyImmediate()` |
| Index without locking the table | `$table->index($cols)->online()` — `CREATE INDEX CONCURRENTLY` |
| Index access method | `$table->index($cols, $name, 'gin')` |
| Arbitrary column type | `$table->rawColumn('c', 'tstzrange')` |
| Vector / full-text | `$table->vector('embedding', 3)`, `$table->vectorIndex('embedding')`, `$table->tsvector('doc')` |
| Table and column comments | `$table->comment('...')`, `$table->string('c')->comment('...')` |

## Contributing

Issues and pull requests are welcome. Before opening a PR run the full gate:

```bash
composer phpcs      # PSR-12 over src and tests
composer phpstan    # level 6 with larastan
composer test       # PHPCS + the whole suite, needs PostgreSQL
```

`composer phpunit-unit` runs the unit suite alone and needs no database.

## License

MIT — see [LICENSE](LICENSE).
