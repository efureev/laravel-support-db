# PHP Laravel Database Support

![](https://img.shields.io/badge/php->=7.4-blue.svg)
![](https://img.shields.io/badge/Laravel->=7.30.3-red.svg)
[![Codacy Badge](https://api.codacy.com/project/badge/Grade/5c8b9e85897f4c65b5a017d16f6af6cb)](https://app.codacy.com/manual/efureev/laravel-support-db)
[![Build Status](https://travis-ci.com/efureev/laravel-support-db.svg?branch=master)](https://travis-ci.com/efureev/laravel-support-db)
![PHP Database Laravel Package](https://github.com/efureev/laravel-support-db/workflows/PHP%20Database%20Laravel%20Package/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/efureev/laravel-support-db/v/stable?format=flat)](https://packagist.org/packages/efureev/laravel-support-db)
[![Total Downloads](https://poser.pugx.org/efureev/laravel-support-db/downloads)](https://packagist.org/packages/efureev/laravel-support-db)
[![Maintainability](https://api.codeclimate.com/v1/badges/5c2f433a24871b1f12e3/maintainability)](https://codeclimate.com/github/efureev/laravel-support-db/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/5c2f433a24871b1f12e3/test_coverage)](https://codeclimate.com/github/efureev/laravel-support-db/test_coverage)

## Description

### Custom types

Extending base types of Schemas for following DB:
- Postgres
    - numeric
    - tsRange
    - auto-generated UUID
- nothing...

### Custom action of Blueprint

- bit
- tsRange
- numeric
- generateUUID
- primaryUUID
- ifNotExists
- hasIndex
- createView
- dropView
- uniquePartial
- dropUniquePartial

## Install

```bash
composer require efureev/laravel-support-db "^0.0.1"
```

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

### Create views

Example:
```php
// Facade methods:
Schema::createView('active_users', "SELECT * FROM users WHERE active = 1");
Schema::dropView('active_users');

// Schema methods:
use \Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create('users', function (Blueprint $table) {
    $table
        ->createView('active_users', "SELECT * FROM users WHERE active = 1")
        ->materialize();
});
```


### Extended unique indexes creation

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

`$table->dropUnique()` doesn't work for Partial Unique Indexes, because PostgreSQL doesn't
define a partial (ie conditional) UNIQUE constraint. If you try to delete such a Partial Unique
Index you will get an error.

```SQL
CREATE UNIQUE INDEX CONCURRENTLY examples_new_col_idx ON examples (new_col);
ALTER TABLE examples
    ADD CONSTRAINT examples_unique_constraint
    USING INDEX examples_new_col_idx;
```

When you create a unique index without conditions, PostgresSQL will create Unique Constraint
automatically for you, and when you try to delete such an index, Constraint will be deleted 
first, then Unique Index. 


## Test

```bash
composer test
composer test-cover # with coverage
```
