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

Extending base types of Schemas for following DB:
- Postgres
    - numeric
    - tsRange
    - auto-generated UUID
- nothing...

## Install

```bash
composer require efureev/laravel-support-db "^0.0.1"
```

## Usage

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
