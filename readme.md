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

## Install

```bash
composer require efureev/laravel-support-db
```

It registers itself through package discovery — no provider to add, no config to publish. Type-hint
the package's `Blueprint` in migrations to get the additions in your editor:

```php
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

Schema::create('table', static function (Blueprint $table) { /* … */ });
```

| | Version |
|---|---|
| PHP | >= 8.5 |
| Laravel | >= 13.0 (`illuminate/database`) |
| PostgreSQL | 13 – 18, every one of them exercised in CI |

`compression()` needs PostgreSQL 14 and `nullsNotDistinct()` needs 15; everything else works from
13. The package takes effect on `pgsql` connections only — other drivers on the same application
are untouched.

## Documentation

Full documentation lives in [`docs/`](docs/readme.md).

| Page | Covers |
|---|---|
| [Getting started](docs/installation.md) | Requirements, version floors, how the package hooks in, a first migration |
| [Columns](docs/column-types.md) | The PostgreSQL-only column types, UUID key generation, TOAST compression |
| [Indexes](docs/indexes.md) | Partial and unique-partial indexes, predicates, modifiers, GIN operator classes |
| [Views](docs/views.md) | Plain and materialized views — create, refresh, inspect, drop, other schemas |
| [Schema operations](docs/schema.md) | `CREATE TABLE … LIKE / AS TABLE / AS SELECT`, `DROP … CASCADE`, extensions |
| [Query builder](docs/query-builder.md) | `RETURNING` on `UPDATE` and `DELETE` |
| [Recipes](docs/recipes.md) | Thirteen realistic problems worked end to end |
| [Behaviour notes](docs/behaviour.md) | The things that are easy to trip over, and what Laravel 13 already does itself |
| [Testing & contributing](docs/contributing.md) | Running the suite, and the gate a pull request has to pass |

## What it adds

Partial and unique-partial indexes with a fluent `WHERE` · `CREATE INDEX CONCURRENTLY` and
`NULLS NOT DISTINCT` on them · GIN indexes with an operator class · views, including materialized
ones, with schema-qualified lookups · `CREATE TABLE … LIKE / AS SELECT / AS TABLE` ·
`DROP TABLE … CASCADE` · `CREATE` / `DROP EXTENSION` · column compression ·
`UPDATE` / `DELETE … RETURNING` · and shorthands for `bit`, `numeric`, `xml`, `cidr`, `daterange`,
`tsrange`, the geometric types, the array types, and UUID primary keys that need no extension.

## Contributing

Issues and pull requests are welcome — see [Testing & contributing](docs/contributing.md) for the
gate CI runs.

## License

MIT — see [LICENSE](LICENSE).
