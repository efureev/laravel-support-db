# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog]
and this project adheres to [Semantic Versioning][semver].

Check MD [online][check-online].

## [unreleased]

### Changed

- Raise the minimum PHP version to 8.5 (BC break, targeted at 5.0.0). Laravel 13 itself only
  requires PHP 8.3, so this is a deliberate choice by the package; no code in `src/` relies on
  PHP 8.4/8.5-only features.
- CI now tests PHP 8.5 only; the Docker image is based on `php:8.5-cli-alpine`.

### Added

- `Schema::dropViewIfExists($view, $materialize = false)` and
  `Schema::refreshMaterializedView($view, $concurrently = false)`.
- `dropView()` accepts a `$materialize` flag, so materialized views can finally be dropped.
- A database-free `Unit` test suite asserting on generated SQL, split out from `Functional`
  in `phpunit.xml`. Run it with `composer phpunit-unit`.

### Removed

- `Blueprint::hasIndex()`. It resolved the `Schema` facade, i.e. the default connection, ignoring
  the blueprint's own — use the framework's `Schema::hasIndex($table, $index, $type)`, or
  `Schema::connection($name)->hasIndex(...)` to be explicit about the connection.
- The `ginIndex()` and `algorithm()` column modifiers. They were only ever phpdoc: Laravel turns a
  fixed set of column attributes into index commands and neither was part of it, so the calls
  silently did nothing. They now throw a `BadMethodCallException` pointing at the working form.
  The table-level `$table->ginIndex($columns)` is unaffected and keeps working.

### Fixed

- `Grammar::addModifier()` used the array union operator on a list, silently overwriting the
  `Collate` modifier. `->collation()` produced no `collate` clause for every user of the package.
- `->compression()` combined with `->change()` emitted `alter column "x"  compression y`, which is
  not valid PostgreSQL, and repeated every `SET COMPRESSION` once per changed column.
- Dropped the dependency on `Blueprint::getChangedColumns()`, deprecated in Laravel 13.
- `->compression()` without an argument produced `compression 1` instead of `compression pglz`.
  The method is now validated and rejects anything that is not a bare identifier.
- Values in partial-index predicates were interpolated without escaping, so an apostrophe broke
  the statement, and non-strings were cast with `(int)` — turning `3.14` into `3` and `null` into `0`.
- `whereRaw()` built a `sprintf()` format from the raw SQL, so a literal `%` raised
  `ArgumentCountError`.
- `CreateCompiler` collapsed every double space in the statement, corrupting default values and
  user-supplied `fromSelect()` SQL.
- Partial and partial-unique indexes ignored the connection's table prefix and left identifiers
  unquoted, producing indexes that targeted a non-existent relation.
- Any non-where call on `uniquePartial()` (such as `->algorithm()`) turned the index into a partial
  one with an empty `WHERE`; repeated `where()` calls on the same builder discarded the earlier ones.
- `hasView()` and `getViewDefinition()` now look in `pg_matviews` as well, so materialized views
  are found.
- `where()` on an index predicate accepts `int`, `float`, `bool`, `BackedEnum`, `DateTimeInterface`
  and `null`, not only `string`.
- The `$algorithm` argument of `partial()` and `uniquePartial()` is finally compiled into
  `using <method>`. The fluent form `->algorithm('gin')` now also survives on a partial unique
  index, where it used to be dropped whenever a predicate was present. The value is validated as
  a bare identifier.
- `updateAndReturn()` / `deleteAndReturn()` no longer leave an array in
  `Connection::$recordsModified`, which is a bool and feeds the sticky-connection check.

### Changed

- `createViewOrReplace(..., materialize: true)` throws a `LogicException`: PostgreSQL has no
  `CREATE OR REPLACE` for materialized views. It previously emitted invalid SQL.
- Generated SQL is lowercase throughout, matching the rest of the framework.

## [4.0.0] - 2026-06-04

### Added

- Add support for Laravel 13
- Add support for PHP 8.4 (tested against PHP 8.5)
- Add Docker-based test environment (`docker-compose.yml` with PostgreSQL 18, `composer test:docker`)

### Changed

- `Blueprint::generateUUID()` now defaults to the native `gen_random_uuid()` (PostgreSQL >= 13)
  instead of the `uuid-ossp` extension (`uuid_generate_v4()`); no implicit `CREATE EXTENSION` is executed anymore
- Upgrade test runner to PHPUnit 13
- Modernize GitHub Actions CI (PostgreSQL 18, PHP 8.4/8.5 matrix, composer cache, dropped CodeClimate coverage)

### Removed

- Remove support for Laravel < 13
- Remove support for PHP < 8.4
- Remove the implicit `uuid-ossp` extension dependency from `generateUUID()`
- Remove the obsolete `.travis.yml`

## [3.0.0] - 2025-02-24

### Added

- Add a support for Laravel 12

## [2.1.0] - 2024-07-22

### Added

- Add `TextArrayType`

## [2.0.0] - 2024-03-13

### Added

- Add support Laravel 11

### Removed

- Remove support Laravel < 11
- Remove support PHP < 8.2

## [1.11.0] - 2024-01-31

### Added

- Add support PHP 8.3

## [1.10.0] - 2023-03-27

### Added

- Add `Partial index`

## [1.9.0] - 2023-02-24

### Added

- Add support PHP 8.2
- Add support Laravel 10

### Removed

- Remove support PHP 8.0
- Remove support Laravel 8|9

## [1.8.1] - 2022-09-08

### Fixed

- Fixed type declarations

## [1.8.0] - 2022-08-18

### Added

- Add support PHP 8.1

## [1.7.0] - 2022-02-08

### Added

- Add ext-column types
- Geo Point (native PG `point` type)
- Geo Path (native PG `path` type)

## [1.6.0] - 2021-11-15

### Added

- Add to `Builder` method `updateAndReturn`: Update records in the database and return columns of updated records
- Add to `Builder` method `deleteAndReturn`: Delete records in the database and return columns of deleted records

## [1.5.0] - 2021-11-04

### Added

- Add to `Blueprint` method `fromTable`: Create a table from another table and fills it data from the source-table
- Add to `Blueprint` method `fromSelect`: Create a table from select query

## [1.4.0] - 2021-11-04

### Added

- Add to `Schema` method `dropIfExistsCascade`
- Add to `Blueprint` method `like`: Create a table from another table

## [1.3.0] - 2021-10-16

### Added

- Add column options: `Compression`
- Add index: `ginIndex`

## [1.2.0] - 2021-10-16

### Added

- Add ext-column types
  - Array of UUID
  - Array of Integer

## [1.1.0] - 2021-09-27

### Added

- Add ext-column types
  - Date Range
  - IP Network
  - XML
- Add to `Schema` method `createViewOrReplace`
- Add helpers for `Extensions`:
  - `createExtension`
  - `createExtensionIfNotExists`
  - `dropExtensionIfExists`

## [1.0.0] - 2021-04-28

### Changed

- Change minimal PHP version to `8.0`. Version of this package for php7 - see in branch `php7`

## [0.0.3] - 2021-02-11

### Added

- Add Bool `wheres` on Partial index

## [0.0.1] - 2021-01-27

### Added

- Create the package

[unreleased]: https://github.com/efureev/laravel-support-db/compare/v4.0.0...HEAD

[4.0.0]: https://github.com/efureev/laravel-support-db/compare/v3.0.0...v4.0.0

[3.0.0]: https://github.com/efureev/laravel-support-db/compare/v2.1.0...v3.0.0

[2.1.0]: https://github.com/efureev/laravel-support-db/compare/v2.0.0...v2.1.0

[2.0.0]: https://github.com/efureev/laravel-support-db/compare/v1.11.0...v2.0.0

[1.11.0]: https://github.com/efureev/laravel-support-db/compare/v1.10.0...v1.11.0

[1.10.0]: https://github.com/efureev/laravel-support-db/compare/v1.9.0...v1.10.0

[1.9.0]: https://github.com/efureev/laravel-support-db/compare/v1.8.1...v1.9.0

[1.8.1]: https://github.com/efureev/laravel-support-db/compare/v1.8.0...v1.8.1

[1.8.0]: https://github.com/efureev/laravel-support-db/compare/v1.7.0...v1.8.0

[1.7.0]: https://github.com/efureev/laravel-support-db/compare/v1.6.0...v1.7.0

[1.6.0]: https://github.com/efureev/laravel-support-db/compare/v1.5.0...v1.6.0

[1.5.0]: https://github.com/efureev/laravel-support-db/compare/v1.4.0...v1.5.0

[1.4.0]: https://github.com/efureev/laravel-support-db/compare/v1.3.0...v1.4.0

[1.3.0]: https://github.com/efureev/laravel-support-db/compare/v1.2.0...v1.3.0

[1.2.0]: https://github.com/efureev/laravel-support-db/compare/v1.1.0...v1.2.0

[1.1.0]: https://github.com/efureev/laravel-support-db/compare/v1.0.0...v1.1.0

[1.0.0]: https://github.com/efureev/laravel-support-db/releases/tag/v1.0.0

[0.0.3]: https://github.com/efureev/laravel-support-db/releases/tag/v0.0.3

[0.0.2]: https://github.com/efureev/laravel-support-db/releases/tag/v0.0.2

[0.0.1]: https://github.com/efureev/laravel-support-db/releases/tag/v0.0.1

[keepachangelog]:https://keepachangelog.com/en/1.1.0/

[semver]:https://semver.org/spec/v2.0.0.html

[check-online]:https://dlaa.me/markdownlint
