# PHP Laravel Database Support

![](https://img.shields.io/badge/php->=8.0-blue.svg)
![](https://img.shields.io/badge/Laravel->=7.30.3-red.svg)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/5c8b9e85897f4c65b5a017d16f6af6cb)](https://app.codacy.com/manual/efureev/laravel-support-db)
![PHP Database Laravel Package](https://github.com/efureev/laravel-support-db/workflows/PHP%20Database%20Laravel%20Package/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-support-db/v/stable?format=flat)](https://packagist.org/packages/efureev/laravel-support-db)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-support-db/downloads)](https://packagist.org/packages/efureev/laravel-support-db)
[![Maintainability](https://api.codeclimate.com/v1/badges/97e244f2aa0ad5b425c5/maintainability)](https://codeclimate.com/github/efureev/laravel-support-db/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/97e244f2aa0ad5b425c5/test_coverage)](https://codeclimate.com/github/efureev/laravel-support-db/test_coverage)

## Description

## Install

```bash
composer require efureev/laravel-support-db "^1.1.0"
```

## Contents

- [Ext Column Types](#ext-column-types)
  - [Bit](#bit)
  - [IP Network](#ip-network)
  - [Ranges](#ranges)
  - [UUID](#uuid)
  - [XML](#xml)
- [Views](#views)
- [Indexes](#indexes)
  - [Unique Partial indexes](#unique-partial-indexes)
- [Extensions](#extensions)

### Ext Column Types

#### Bit

Bit String

```php
// @see https://www.postgresql.org/docs/current/datatype-bit.html
$table->bit(string $column, int $length = 1);
```

#### IP Network

The IP network datatype stores an IP network in CIDR notation.

IPv4 = 7 bytes  
IPv6 = 19 bytes

```php
// @see https://www.postgresql.org/docs/current/datatype-net-types.html
$table->ipNetwork(string $column);
```

#### Ranges

The range data types store a range of values with optional start and end values. They can be used e.g. to describe the
duration a meeting room is booked.

```php
// @see https://www.postgresql.org/docs/current/rangetypes.html
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

```php
// @see https://www.postgresql.org/docs/current/datatype-xml.html
$table->xml(string $column);
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
