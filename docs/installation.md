# Getting started

[← Documentation](readme.md) · [Package readme](../readme.md)

---

Requirements, install, and how the package hooks itself into Laravel.

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
use Illuminate\Database\Migrations\Migration;
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
