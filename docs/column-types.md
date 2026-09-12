# Columns

[← Documentation](readme.md) · [Package readme](../readme.md)

---

The PostgreSQL-only column types, UUID key generation, and TOAST compression.

## Column types

Each method returns a `ColumnDefinition`, so the framework's modifiers (`nullable()`, `default()`,
`index()`, `comment()`, …) chain off it as usual.

| Method | Column type | PostgreSQL docs |
|---|---|---|
| `bit(string $column, int $length)` | `bit(n)` | [Bit string](https://www.postgresql.org/docs/current/datatype-bit.html) |
| `numeric(string $column, ?int $precision = null, ?int $scale = null)` | `numeric`, `numeric(p)`, `numeric(p, s)` | [Numeric](https://www.postgresql.org/docs/current/datatype-numeric.html) |
| `dateRange(string $column)` | `daterange` | [Range types](https://www.postgresql.org/docs/current/rangetypes.html) |
| `tsRange(string $column)` | `tsrange` | [Range types](https://www.postgresql.org/docs/current/rangetypes.html) |
| `timestampRange(string $column)` | `tsrange` (alias of `tsRange`) | [Range types](https://www.postgresql.org/docs/current/rangetypes.html) |
| `ipNetwork(string $column)` | `cidr` | [Network types](https://www.postgresql.org/docs/current/datatype-net-types.html) |
| `geoPoint(string $column)` | `point` | [Geometric types](https://www.postgresql.org/docs/current/datatype-geometric.html) |
| `geoPath(string $column)` | `path` | [Geometric types](https://www.postgresql.org/docs/current/datatype-geometric.html) |
| `xml(string $column)` | `xml` | [XML](https://www.postgresql.org/docs/current/datatype-xml.html) |
| `uuidArray(string $column)` | `uuid[]` | [Arrays](https://www.postgresql.org/docs/current/arrays.html) |
| `textArray(string $column)` | `text[]` | [Arrays](https://www.postgresql.org/docs/current/arrays.html) |
| `intArray(string $column)` | `integer[]` | [Arrays](https://www.postgresql.org/docs/current/arrays.html) |

`numeric()` differs from the framework's `decimal()` in that both arguments are optional — omit
them for an unconstrained `numeric`, which stores any precision:

```php
$table->numeric('amount');          // numeric
$table->numeric('amount', 10);      // numeric(10)
$table->numeric('amount', 10, 2);   // numeric(10, 2)
```

> `information_schema` normalises some of these on the way back: all three array types report as
> `ARRAY`, and `numeric(10)` reads as `numeric(10,0)`. That is PostgreSQL, not the package — the
> emitted DDL is exactly what the table above says.

## UUID keys

`primaryUUID()` is a UUID column plus its primary key; `generateUUID()` is the column alone. Both
default to the native `gen_random_uuid()`, which needs no extension on PostgreSQL 13 and later.

```php
$table->primaryUUID();                // "id" uuid not null default gen_random_uuid() + pk
$table->primaryUUID('uid');           // same, named "uid"
$table->primaryUUID('id', false);     // pk you populate yourself
```

The second argument decides where the value comes from, and `primaryUUID()` passes it straight
through to `generateUUID()`:

| Argument | Result |
|---|---|
| `true` *(default)* | `uuid not null default gen_random_uuid()` |
| `false` | `uuid not null` — no default, you must supply a value |
| `null` | `uuid null` — nullable, no default |
| `Expression` | `uuid not null default <expression>` |
| `callable(string $column): string` | `uuid not null default <the string it returns>` |

```php
use Illuminate\Database\Query\Expression;

$table->generateUUID();                                  // "id", database-generated
$table->generateUUID('cid');                             // "cid", database-generated
$table->generateUUID('tenant_id', null)->index();        // nullable FK column, indexed
$table->generateUUID('external_id', false);              // not null, supplied by the application
$table->generateUUID('id', new Expression('uuid_generate_v4()'));
$table->generateUUID('id', fn (string $c) => "uuid_generate_v5(uuid_ns_url(), '$c')");
```

> The `uuid_generate_*` family comes from `uuid-ossp` — call
> `Schema::createExtensionIfNotExists('uuid-ossp')` first. The default `gen_random_uuid()` is
> built in and needs nothing.

## Column compression

PostgreSQL 14 and later can compress TOAST-able columns with either `pglz` (the historical
algorithm) or `lz4` (faster, usually a better ratio).
[Docs](https://www.postgresql.org/docs/current/storage-toast.html).

```php
$table->text('body')->compression('lz4');      // text compression lz4
$table->text('body')->compression();           // defaults to pglz
$table->text('body')->compression('default');  // the server's default_toast_compression
```

It works on `change()` too, where PostgreSQL takes it as a separate statement:

```php
$table->text('body')->compression('lz4')->change();
// alter table "docs" alter column "body" set compression lz4
```

Only the new rows are compressed with the new method; existing ones keep whatever they were
written with until they are rewritten.
