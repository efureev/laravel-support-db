# PHP Laravel Database Support

![](https://img.shields.io/badge/php->=8.1|8.2-blue.svg)
![](https://img.shields.io/badge/Laravel->=10.1-red.svg)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/5c8b9e85897f4c65b5a017d16f6af6cb)](https://app.codacy.com/manual/efureev/laravel-support-db)
![PHP Database Laravel Package](https://github.com/efureev/laravel-support-db/workflows/PHP%20Database%20Laravel%20Package/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-support-db/v/stable?format=flat)](https://packagist.org/packages/efureev/laravel-support-db)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-support-db/downloads)](https://packagist.org/packages/efureev/laravel-support-db)
[![Maintainability](https://api.codeclimate.com/v1/badges/97e244f2aa0ad5b425c5/maintainability)](https://codeclimate.com/github/efureev/laravel-support-db/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/97e244f2aa0ad5b425c5/test_coverage)](https://codeclimate.com/github/efureev/laravel-support-db/test_coverage)

## Description

## Install

```bash
composer require efureev/laravel-support-db "^1.9"
```

## Contents

- [Ext Column Types](#ext-column-types)
    - [Bit](#bit)
    - [GeoPoint](#geo-point)
    - [GeoPath](#geo-path)
    - [IP Network](#ip-network)
    - [Ranges](#ranges)
    - [UUID](#uuid)
    - [XML](#xml)
    - [Array of UUID](#array-of-uuid)
    - [Array of Integer](#array-of-integer)
- [Column Options](#column-options)
    - [Compression](#compression)
- [Views](#views)
- [Indexes](#indexes)
    - [Partial indexes](#partial-indexes)
    - [Unique Partial indexes](#unique-partial-indexes)
- [Extended Schema](#extended-schema)
    - [Create like another table](#create-like-another-table)
    - [Create as another table with full data](#create-as-another-table-with-full-data)
    - [Create as another table with data from select query](#create-as-another-table-with-data-from-select-query)
    - [Drop Cascade If Exists](#drop-cascade-if-exists)
- [Extended Query Builder](#extended-query-builder)
    - [Update records and return deleted records` columns](#update-records-and-return-updated-records-columns)
    - [Delete records and return deleted records` columns](#delete-records-and-return-deleted-records-columns)
- [Extensions](#extensions)

### Ext Column Types

#### Bit

Bit String.
[Doc](https://www.postgresql.org/docs/current/datatype-bit.html).

```php
$table->bit(string $column, int $length = 1);
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
$table->geoPoint(string $column);
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

#### UUID

The `primaryUUID` can be used to store UUID-type as primary key.

```php
$table->primaryUUID(); // create PK UUID-column with name `id`
$table->primaryUUID('custom_name'); // create PK UUID-column with name `custom_name`
```

The `generateUUID` can be used to store UUID-type with/without index (or FK).

On a row creating generates a value with `uuid_generate_v4()` by extension `uuid-ossp`.

```php
// create UUID-column with name `id`. Generate UUID-value by DB.
$table->generateUUID();

// create UUID-column with name `cid`. Generate UUID-value by DB.
$table->generateUUID('cid');

// create UUID-column with name `cid`. NOT generate UUID-value by DB. Set `nullable`. Default value: `NULL`. 
$table->generateUUID('id', null);

// create UUID-column with name `cid`. NOT generate UUID-value by DB. Set `nullable`. Default value: `NULL`. Create Index by this column.
$table->generateUUID('fk_id', null)->index();

 // create UUID-column with name `fk_id`. NOT generate UUID-value by DB.
$table->generateUUID('fk_id', false);

// create UUID-column with name `fk_id`. Generate UUID-value by DB with custom value.
$table->generateUUID('fk_id', fn($column)=>'uuid_generate_v5()');

// create UUID-column with name `fk_id`. Generate UUID-value by DB with custom value.
$table->generateUUID('fk_id', new Expression('uuid_generate_v2()'));
```

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

### Column Options

#### Compression

PostgreSQL 14 introduced the possibility to specify the compression method for toast-able data types. You can choose
between the default method `pglz`, the recently added `lz4` algorithm and the value `default` to use the server default
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
Schema::createView('active_users', "SELECT * FROM users WHERE active = 1", true) ;
Schema::createViewOrReplace('active_users', "SELECT * FROM users WHERE active = 1");

// Schema methods:
use \Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create('users', function (Blueprint $table) {
    $table
        ->createView('active_users', "SELECT * FROM users WHERE active = 1")
        ->materialize();
});
```

#### Dropping views

```php
// Facade methods:
Schema::dropView('active_users');
Schema::dropViewIfExists('active_users');
```

### Indexes

#### Partial indexes

See: https://www.postgresql.org/docs/current/indexes-partial.html

Example:

```php
use \Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
Schema::create('table', static function (Blueprint $table) {
    $table->string('code'); 
    $table->softDeletes();
    $table
        ->partial('code')
        ->whereNull('deleted_at');
});
```

If you want to delete partial index, use this method:

```php
use \Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create('table', static function (Blueprint $table) {
    $table->dropPartial(['code']);
});
```

#### Unique Partial indexes

Example:

```php
use \Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
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
use \Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

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

When you create a unique index without conditions, PostgresSQL will create Unique Constraint automatically for you, and
when you try to delete such an index, Constraint will be deleted first, then Unique Index.

### Extended Schema

#### Create like another table

Create a table from a source-table. Creates a structure only.  
`includingAll` copies all dependencies from source-table.

Creating will be without a data.

```php
Schema::create('target_table', function (Blueprint $table) {
    $table->like('source_table')->includingAll(); 
    $table->ifNotExists();
});
```

#### Create as another table with full data

Copy a table from a source-table. Copy only columns and a data. Without indexes and so on...

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

// or

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

Schema::create(self::TGT_TABLE, function (Blueprint $table) use ($tbl) {
    DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

    $table->fromSelect(
        'select uuid_generate_v4() as id, key, title, sort from ' . $tbl
    );
});

// or

Schema::create(self::TGT_TABLE, function (Blueprint $table) use ($tbl) {
    DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

    $table->fromSelect(
        'select uuid_generate_v4() as id, * ' . $tbl
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

#### Update records and return updated records` columns

```php
$list = Model::toBase()->updateAndReturn(['deleted_at' => now()], 'id', 'name');
```

```php
$list = Model::where(['enabled' => true])->updateAndReturn(['enabled' => false], 'id');
```

#### Delete records and return deleted records` columns

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
Schema::createExtension('tablefunc');
Schema::createExtensionIfNotExists('tablefunc');
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

-----

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

```bash
composer test
composer test-cover # with coverage
```
