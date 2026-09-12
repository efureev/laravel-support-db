# Documentation

[← Package readme](../readme.md)

---

PostgreSQL features for Laravel's schema and query builders. Start with
[Getting started](installation.md); reach for [Recipes](recipes.md) when you have a problem rather
than a method in mind.

## The shape of it

One live user per address — soft-deleted rows do not collide:

```php
Schema::create('users', static function (Blueprint $table) {
    $table->primaryUUID();
    $table->string('email');
    $table->softDeletes();

    $table->uniquePartial('email')->whereNull('deleted_at');
});
```

```sql
create table "users" ("id" uuid not null default gen_random_uuid(),
    "email" varchar(255) not null,
    "deleted_at" timestamp(0) without time zone null)

create unique index "users_email_unique" on "users" ("email")
    where ("deleted_at" is null)

alter table "users" add primary key ("id")
```

## Reference

| Page | Covers |
|---|---|
| [Getting started](installation.md) | Requirements, version floors, install, how the package hooks in, a first migration |
| [Columns](column-types.md) | The PostgreSQL-only column types, UUID key generation, TOAST compression |
| [Indexes](indexes.md) | Partial and unique-partial indexes, predicates, modifiers, GIN operator classes |
| [Views](views.md) | Plain and materialized views — create, refresh, inspect, drop, other schemas |
| [Schema operations](schema.md) | `CREATE TABLE … LIKE / AS TABLE / AS SELECT`, `DROP … CASCADE`, extensions |
| [Query builder](query-builder.md) | `RETURNING` on `UPDATE` and `DELETE` |

## Practice

| Page | Covers |
|---|---|
| [Recipes](recipes.md) | Thirteen realistic problems worked end to end |
| [Behaviour notes](behaviour.md) | The things that are easy to trip over, and what Laravel 13 already does itself |
| [Roadmap](roadmap.md) | Where the package goes next, and what is deliberately not on the list |
| [Testing & contributing](contributing.md) | Running the suite, and the gate a pull request has to pass |

## At a glance

What this package adds, and where each of them is documented:

| Feature | Documented in |
|---|---|
| Partial and unique-partial indexes, with predicates | [Indexes](indexes.md) |
| `CREATE INDEX CONCURRENTLY`, `NULLS NOT DISTINCT` on a partial index | [Indexes](indexes.md) |
| GIN indexes with an operator class | [Indexes](indexes.md) |
| Views, including materialized ones, and schema-qualified lookups | [Views](views.md) |
| `CREATE TABLE … LIKE / AS SELECT / AS TABLE` | [Schema operations](schema.md) |
| `DROP TABLE … CASCADE` | [Schema operations](schema.md) |
| `CREATE` / `DROP EXTENSION` | [Schema operations](schema.md) |
| `UPDATE` / `DELETE … RETURNING` | [Query builder](query-builder.md) |
| Column compression | [Columns](column-types.md) |
| `bit`, `numeric`, `xml`, `cidr`, `daterange`, `tsrange`, geometry, arrays | [Columns](column-types.md) |
| UUID primary keys without an extension | [Columns](column-types.md) |
