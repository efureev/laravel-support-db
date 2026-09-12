# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog]
and this project adheres to [Semantic Versioning][semver].

Check MD [online][check-online].

## [unreleased]

### Added

- `docs/roadmap.md`, and with it a plan that leads with a defect rather than a feature. The
  package creates partial unique indexes and thereby makes Laravel's `upsert()` unusable against
  them — `compileUpsert()` emits `on conflict (col)` with no predicate, and PostgreSQL will not
  infer a partial index without one. Reproduced, and the statement that does work is recorded.
  Alongside it: exclusion constraints, which are what the `tsrange` and `daterange` columns
  already in the package exist for, and which compose with the `btree_gist` its extension methods
  already install. Everything on the list was checked against Laravel 13 first, and what the
  framework already does — generated columns, `DISTINCT ON`, lateral joins, vector and full-text —
  is listed as deliberately excluded so it does not get built twice

### Fixed

- The documentation guard reported content as missing when it was not. Tags are replaced by a
  space before the text is searched, so an inline `<code>` left a gap the markdown never had and
  a list item ending in one never matched. It compares with whitespace removed now, which retires
  the whole class. Re-checked that it still catches real loss: dropping a page from the generator
  and disabling list rendering each still fail it

## [5.0.5] - 2026-09-12

### Changed

- The Packagist description says what the package does. It read "PHP Support Package for Laravel
  DB", which mentions neither PostgreSQL nor anything the package adds, and the keywords were as
  vague. Both now name the features, so the package can be found by searching for them
- The Composer package ships `src/`, `composer.json`, `LICENSE`, `readme.md` and `.meta.php` and
  nothing else — 39 files rather than 107, 110 KB rather than 480. `tests/`, `docs/`, `tools/`,
  the CI configuration and the Docker setup are not something a consumer needs in `vendor/`.
  The mechanism is `.gitattributes` with `export-ignore`: Composer installs from GitHub's zipball,
  which is a `git archive` of the tag, and `archive.exclude` in `composer.json` never reaches it.
  That is set too, for `composer archive`
- The documentation links in `readme.md` are absolute, since `docs/` no longer travels with the
  package and a relative link would dangle in `vendor/`

## [5.0.4] - 2026-09-12

### Removed

- `AUDIT.md`. It held no open work: everything it listed is either done or is a record of
  something deliberately not done. The four decisions worth keeping — why `hasView()`,
  `getViewDefinition()`, `ginIndex()` and the `…AndReturn()` methods are not framework
  duplication, why the PHP floor is a choice rather than a requirement, and the rule to call
  `parent::` rather than copy a framework method — moved to `docs/contributing.md`, where the
  people they concern will find them. The five test docblocks that cited it by section number
  already explained themselves, so they simply lost the pointer

## [5.0.3] - 2026-09-12

### Fixed

- The documentation had the transaction rule for `CONCURRENTLY` backwards. It said Laravel does
  not wrap migrations in a transaction, when `Migration::$withinTransaction` is `true` and the
  Postgres grammar reports schema-transaction support, so the migrator does wrap every one — and
  it told readers to set the flag the wrong way round, steering them away from the only fix. Both
  `docs/indexes.md` and `docs/behaviour.md` now say what the framework actually does
- Index predicates described `DateTimeInterface` values as ISO-8601. They are formatted
  `Y-m-d H:i:s`, with no offset, so against a `timestamptz` column PostgreSQL reinterprets them in
  the server's `TimeZone` and silently moves the boundary. Documented as what it is, with the
  workaround
- The Quick start migration extended `Migration` without importing it, so copying it verbatim gave
  `Class "Migration" not found` before any SQL ran
- The per-tenant recipe interpolated a tenant's schema name and id straight into DDL. A view
  definition genuinely cannot take bindings, so it now validates the identifier and escapes the
  literal, the same two rules the package applies to an index algorithm and a compression method
- The generated page had no doctype, charset, viewport or language, so browsers rendered it in
  quirks mode and phones never reached the mobile layout. It is a complete document now, which
  also retires the entity-escaping machinery that existed only because the charset was undeclared
- `build-docs-site.py` crashed on any `#`-leading line that was not a heading, looped forever on
  one that was not a heading either, flattened lists into a run-on paragraph, let an unpaired quote
  paint the rest of a code block as a string, aliased `../readme.md` onto the docs index through
  `lstrip`, and silently dropped links it could not resolve. All six are fixed, the last one by
  refusing to build
- The Pages workflow granted no Pages scope to the job that calls `configure-pages`, never ran on a
  pull request, and guarded only code blocks. It now declares its permissions, renders without
  deploying on a PR, and checks pages, code blocks, headings, table rows and list items — verified
  by dropping a page from the generator and by disabling list rendering, each of which the guard
  now catches

## [5.0.2] - 2026-09-12

### Fixed

- The version in the documentation site's header was hardcoded in the generator, so the published
  page kept claiming 5.0.0 after 5.0.1 shipped. It is read from the newest version heading in this
  file instead — the release record, and unlike a git tag it survives the shallow checkout CI does.
  An `unreleased` heading is skipped, not being a version yet

## [5.0.1] - 2026-09-12

### Changed

- The documentation is rewritten and split across `docs/`, leaving `readme.md` as an overview that
  links into it. Every method now carries its signature, the SQL it emits and the PostgreSQL docs
  it corresponds to; `docs/recipes.md` works through thirteen realistic problems end to end, and
  `docs/behaviour.md` collects the version floors, the transaction rules for `CONCURRENTLY`, and
  the rest of what used to be scattered through the prose. Every example was executed against
  PostgreSQL before it was committed
- `docs/` is the single source of truth for the documentation. `tools/build-docs-site.py` renders
  it into a single self-contained HTML page, so the two cannot drift: a php fence followed
  immediately by a sql one becomes a call-and-emits pair, blockquotes become notes, and everything
  else maps straight across. Porting the page's own additions back into `docs/` turned up an
  example whose emitted SQL was wrong in two ways — the timestamp type and the order of the
  statements — which is what running it against a server rather than writing it from memory is for
- A `Documentation site` workflow renders `docs/` and publishes it to GitHub Pages at
  <https://efureev.github.io/laravel-support-db/>. It runs only when `docs/`, the generator or its
  stylesheet change, keeps nothing generated in the repository, and refuses to deploy a page that
  lost a code block on the way

## [5.0.0] - 2026-09-12

### Added

- `Schema::dropViewIfExists($view, $materialize = false)` and
  `Schema::refreshMaterializedView($view, $concurrently = false)`
- `dropView()` accepts a `$materialize` flag, so materialized views can finally be dropped
- A database-free `Unit` test suite asserting on generated SQL, split out from `Functional` in
  `phpunit.xml`. Run it with `composer phpunit-unit`
- PHPStan is on level 6 with larastan, covers `src` and `tests`, and runs in CI — it used to sit on
  level 1, never run there, and fail when it did. `src` is analysed without a single exemption; the
  handful of ignores are scoped to `tests` and cover only the extension points static analysis
  cannot follow through the `Schema` facade and `Fluent`'s magic dispatch
- `composer audit` runs in CI, and `squizlabs/php_codesniffer` is pinned past CVE-2026-67434
- CI actions are on their current majors: `checkout@v7`, `cache@v6`, `upload-artifact@v7` and
  `action-gh-release@v3`, all of which are Node 24 runtime bumps that leave the inputs this
  workflow passes untouched. `setup-php@v2` and `markdown-lint@v1` stay put — their floating major
  tags already resolve to the latest release
- Every signature in `src` and the test helpers carries its array and generic types. Annotating them
  turned up three latent problems: three where-clause compilers took a `$where = []` default that no
  caller could ever satisfy, a docblock sat below its `#[\Override]` attribute where PHP never reads
  it, and ten docblocks had been stacked so that only the last one counted
- PHPCS now lints `tests` as well as `src` — which is how a PSR-4 violation had gone unnoticed in
  `ArrayOfTextTest`, now fixed along with converting the test migration to the anonymous-class form
  Laravel has used since 9
- PHPUnit fails on warnings, notices, deprecations, risky tests and output during tests
- Each test runs inside a transaction that is rolled back, so isolation no longer depends on the
  order tests happen to run in and the suite is roughly twice as fast. `CREATE EXTENSION` rolls back
  with everything else, where it used to outlive the run — `db:wipe` does not drop extensions. The
  wipe still happens, once per process, because without it a single stray table left by an earlier
  crash cascades into dozens of confusing failures
- CI runs the suite against PostgreSQL 13 through 18 — it used to test one version while the
  assertions depend on PostgreSQL's own SQL rendering. It also publishes a coverage report as an
  artifact, cancels superseded runs, and creates a release for every `v*` tag rather than only
  `v*.0`, which silently skipped every patch release
- `composer test:docker` works on a fresh clone. The bind mount used to shadow the `vendor/` built
  into the image, so the run died on a missing binary unless you happened to have installed
  dependencies on the host. PHP and PostgreSQL versions are now overridable: `POSTGRES_VERSION=15
  composer test:docker`
- `readme.md` gains a description, a requirements table (including the PostgreSQL versions the
  features need), sections for `numeric()` and GIN indexes, a note on what Laravel 13 now does
  natively, and Contributing/License sections
- `.meta.php` covers `Query\Builder` — the block was commented out, so the documented
  `Model::toBase()->updateAndReturn(...)` had no IDE support at all — and `ColumnDefinition`, which
  is what makes `->compression()` visible on an ordinary column
- `ginIndex($columns, $name, $operatorClass)` takes an operator class — `jsonb_path_ops` for
  containment queries on jsonb, `gin_trgm_ops` for trigram search. The framework accepts one on
  spatial and vector indexes only: `index()` has no such argument and `compileIndex()` discards the
  attribute, so `compileIndex()` is overridden to route a command that carries one through the
  framework's own compiler for it
- `->online()` on a partial or unique-partial index emits `CREATE INDEX CONCURRENTLY`, so the
  index is built without taking a write lock on the table. The framework offers this for ordinary
  indexes only. PostgreSQL refuses the statement inside a transaction block, which the suite
  asserts rather than assumes
- `->nullsNotDistinct()` on a unique partial index emits `NULLS NOT DISTINCT`, so at most one row
  may hold a null in the indexed column (PostgreSQL 15 and later). It sits between the column list
  and the predicate. On a non-unique `partial()` it throws: PostgreSQL parses the clause there and
  then ignores it, so accepting it would mean quietly doing nothing
- An `orWhere…` spelling for each of the twelve index predicates. The `$boolean` argument was
  always there; a disjunction now reads as one instead of ending in a stray `'or'`
- The view lookups accept a `schema.view` reference. `createView()` and `dropView()` always did
  through `wrapTable()`; `hasView()` and `getViewDefinition()` bound the current schema and the
  whole dotted string as the name, so a view created in another schema through this builder could
  never be found again. A three-part reference is rejected with the framework's own message.
  Case is left alone, unlike the framework's `hasView()`, which lowercases both sides: this package
  quotes identifiers, so `createView('MyView', ...)` really does make a view named `MyView`
- `addExtendedCommand()` is held to the fields the framework's own `createCommand()` produces. The
  method is a deliberate copy — `addCommand()` hard-codes `Fluent` and offers no hook for a command
  class of one's own — and a copy is the thing that drifts: were a later Laravel to set another
  field, ours would quietly stop setting it
- The `pgsql` resolver closure is held to capturing nothing, neither a bound `$this` nor an imported
  variable. `Connection::$resolvers` is static and the framework never clears it, so a captured
  container would outlive the request, or the Testbench case, that registered it

### Changed

- Raise the minimum PHP version to 8.5 (BC break, targeted at 5.0.0). Laravel 13 itself only
  requires PHP 8.3, so this is a deliberate choice by the package; no code in `src/` relies on PHP
  8.4/8.5-only features
- CI now tests PHP 8.5 only; the Docker image is based on `php:8.5-cli-alpine`
- Grammar methods Laravel dispatches by name no longer narrow their parameters to this package's own
  `Blueprint` / `ColumnDefinition` subclasses. Narrowing worked only while every blueprint happened
  to be built by this package; a custom `blueprintResolver`, a `BlueprintState` or a third-party
  macro made it a fatal `TypeError`
- `compileCreate()` hands a plain `create table` back to the parent grammar and only takes over when
  the blueprint actually uses `like()`, `fromSelect()`, `fromTable()` or `ifNotExists()`, so
  framework improvements are no longer silently discarded
- `#[\Override]` on all 15 overrides, and `declare(strict_types=1)` in the two files that lacked
  it. Return and parameter types filled in on the extended query builder and schema builder
- `like()` returns a `LikeDefinition` and `createView()` / `createViewOrReplace()` return a
  `ViewDefinition` instead of a bare `Fluent`, so the documented return types are now the real ones
  and `->includingAll()` / `->materialize()` are visible to IDEs
- `createViewOrReplace(..., materialize: true)` throws a `LogicException`: PostgreSQL has no `CREATE
  OR REPLACE` for materialized views. It previously emitted invalid SQL
- Generated SQL is lowercase throughout, matching the rest of the framework
- The PHPCS ruleset drops two rules that are in neither PSR-12 nor PER-CS 2.0 and that IDE
  formatters undo on save: vertical alignment of `=` and one-argument-per-line in every multi-line
  call. `Generic.ControlStructures.InlineControlStructure` was declared twice, and the replacement
  suggested for `is_null` was the meaningless `null` rather than `=== null`. It gains `basepath`,
  so reported paths are no longer truncated absolute ones, plus `cache`, `parallel`, `colors` and
  a `php_version` target
- `Squiz.Arrays.ArrayDeclaration.ValueNoNewline` is silenced. The sniff predates arrow functions
  and reads `fn(...) =>` inside an array as a key separator; phpcbf then breaks the line between
  `fn` and its parameter list and converges on that shape, so running it twice keeps the mangling
  and PHPCS calls the result correct
- The PHPUnit config is `phpunit.xml.dist` and `phpunit.xml` is ignored, which is the usual way
  round and is what makes a local override possible without dirtying the tree. Its `<server>` and
  `<env>` entries are now all `<env>`: Laravel reads both, so mixing them only obscured which one
  a name came through. `force` carries the meaning instead — pinned for `DB_CONNECTION`, `APP_ENV`
  and `APP_KEY`, yielding to the environment for the connection details

### Removed

- The package's `ConnectionFactory`. `pgsql` connections are now routed with
  `Connection::resolverFor()`, which the framework's own factory consults first, so there is nothing
  left to subclass. This also drops the hand-copied `registerConnectionServices()` that had drifted
  from Laravel 13's and silently skipped the `ConcurrencyErrorDetector` and `LostConnectionDetector`
  bindings
- The eleven `Schema\Postgres\Types\*` classes, replaced by the `Schema\Postgres\ColumnType` backed
  enum. `phpType()` is renamed `laravelType()` — it returns what `Schema::getColumnType()` reports,
  never a PHP type
- `PartialDefinition` and `UniqueDefinition`: `partial()` and `uniquePartial()` return the real
  `PartialBuilder` / `UniqueBuilder`, which is what the signatures now say
- `UniquePartialBuilder`, which was byte-identical to `PartialBuilder`
- `Blueprint::hasIndex()`. It resolved the `Schema` facade, i.e. the default connection, ignoring
  the blueprint's own — use the framework's `Schema::hasIndex($table, $index, $type)`, or
  `Schema::connection($name)->hasIndex(...)` to be explicit about the connection
- The `ginIndex()` and `algorithm()` column modifiers. They were only ever phpdoc: Laravel turns a
  fixed set of column attributes into index commands and neither was part of it, so the calls
  silently did nothing. They now throw a `BadMethodCallException` pointing at the working form. The
  table-level `$table->ginIndex($columns)` is unaffected and keeps working

### Fixed

- `whereBetween()` accepted any number of values: none produced `between false and false`, one
  produced `between 1 and 1`, and three silently dropped the middle. It now insists on exactly two
- `partial([])` and `uniquePartial([])` compiled to `on "t" ()`, which PostgreSQL rejects; they now
  refuse an empty column list
- `dropExtensionIfExists()` with no arguments emitted `drop extension if exists` and nothing else
- A modifier written after a predicate on `uniquePartial()` was silently dropped, while the same
  modifier written before one worked. `UniqueBuilder::__call` handed back the constraint builder,
  so `->whereNull('deleted_at')->algorithm('btree')` set the access method on the predicates rather
  than on the index and compiled without a `USING` clause. It returns the command now, and the
  order no longer matters
- `CreateIndexTest` carried two tests with byte-identical bodies under the group names `WithSchema`
  and `WithoutSchema`, promising a difference in `search_path` that neither body made. The
  difference is real now: one asserts the index lands in the default schema, the other creates a
  schema and asserts the index follows the session's `search_path` rather than the one in the
  connection config

- `CompressionTest` asserted only that the table exists, so the compression modifier was effectively
  untested. It now reads `pg_attribute.attcompression` back, covers `lz4` as well as `pglz`, and
  skips below PostgreSQL 14 — the version the feature needs
- The index helper read `pg_indexes` without an `ORDER BY` while `CreateTableLikeTest` indexed the
  result positionally, asserting on an order PostgreSQL does not promise. Adding the ordering is
  what exposed it; the test now compares sets of index names
- Index assertions no longer hardcode the `public.` schema prefix, and `CreateViewTest` drops its
  table with cascade so an aborted test cannot leave a dependent view behind and have the failing
  teardown mask the original error
- View assertions no longer depend on how a particular PostgreSQL renders a view: 15 qualifies
  column names in `pg_get_viewdef()` output and 16 does not, which stopped the suite from running on
  anything below 16
- `RETURNING` rows from `updateAndReturn()` / `deleteAndReturn()` were fetched with a hardcoded
  `PDO::FETCH_ASSOC` that bypassed `Connection::prepared()`. They were the only result set on the
  connection shaped as arrays, a configured fetch mode was ignored, and the `StatementPrepared`
  event — which packages hook to change that mode — never fired. **They now come back in the
  connection's fetch mode, `stdClass` by default, like every other result set.**
- Broken `readme.md` examples: the Geo Path section showed `geoPoint()`, `bit()` was documented with
  a default it never had, a `fromSelect()` example was missing its `from`, the UUID examples called
  `uuid_generate_v2()` (no such function) and `uuid_generate_v5()` without its arguments, and two
  snippets referenced a constant lifted out of the test suite
- `CHANGELOG.md`: seven released tags had no entry
  (0.0.2, 1.0.1, 1.3.1, 1.4.1, 1.6.1, 2.2.0, 2.2.1), three dates disagreed with their tags, and the
  compare links skipped the missing releases. The changelog linter's date pattern was hardcoded to
  `20[12][0-9]` and would have rejected every header from 2030 on
- `Grammar::addModifier()` used the array union operator on a list, silently overwriting the
  `Collate` modifier. `->collation()` produced no `collate` clause for every user of the package
- `->compression()` combined with `->change()` emitted `alter column "x"  compression y`, which is
  not valid PostgreSQL, and repeated every `SET COMPRESSION` once per changed column
- Dropped the dependency on `Blueprint::getChangedColumns()`, deprecated in Laravel 13
- `->compression()` without an argument produced `compression 1` instead of `compression pglz`. The
  method is now validated and rejects anything that is not a bare identifier
- Values in partial-index predicates were interpolated without escaping, so an apostrophe broke the
  statement, and non-strings were cast with `(int)` — turning `3.14` into `3` and `null` into `0`
- `whereRaw()` built a `sprintf()` format from the raw SQL, so a literal `%` raised
  `ArgumentCountError`
- `CreateCompiler` collapsed every double space in the statement, corrupting default values and
  user-supplied `fromSelect()` SQL
- Partial and partial-unique indexes ignored the connection's table prefix and left identifiers
  unquoted, producing indexes that targeted a non-existent relation
- Any non-where call on `uniquePartial()` (such as `->algorithm()`) turned the index into a partial
  one with an empty `WHERE`; repeated `where()` calls on the same builder discarded the earlier ones
- `hasView()` and `getViewDefinition()` now look in `pg_matviews` as well, so materialized views are
  found
- `where()` on an index predicate accepts `int`, `float`, `bool`, `BackedEnum`, `DateTimeInterface`
  and `null`, not only `string`
- The `$algorithm` argument of `partial()` and `uniquePartial()` is finally compiled into the
  index access method. The fluent form `->algorithm('gin')` now also survives on a partial unique
  index, where it used to be dropped whenever a predicate was present. The value is validated as a
  bare identifier
- `updateAndReturn()` / `deleteAndReturn()` no longer leave an array in
  `Connection::$recordsModified`, which is a bool and feeds the sticky-connection check

## [4.0.0] - 2026-06-04

### Added

- Add support for Laravel 13
- Add support for PHP 8.4 (tested against PHP 8.5)
- Add Docker-based test environment (`docker-compose.yml` with PostgreSQL 18, `composer
  test:docker`)

### Changed

- `Blueprint::generateUUID()` now defaults to the native `gen_random_uuid()` (PostgreSQL >= 13)
  instead of the `uuid-ossp` extension (`uuid_generate_v4()`); no implicit `CREATE EXTENSION` is
  executed anymore
- Upgrade test runner to PHPUnit 13
- Modernize GitHub Actions CI (PostgreSQL 18, PHP 8.4/8.5 matrix, composer cache, dropped
  CodeClimate coverage)

### Removed

- Remove support for Laravel < 13
- Remove support for PHP < 8.4
- Remove the implicit `uuid-ossp` extension dependency from `generateUUID()`
- Remove the obsolete `.travis.yml`

## [2.2.1] - 2025-02-24

### Added

- Allow `illuminate/database` `^12.0` and `orchestra/testbench` `^10.0` alongside 11.x

## [2.2.0] - 2024-12-25

### Added

- PHPStan configuration

### Changed

- Reworked the PHPUnit configuration and the test environment setup

## [3.0.0] - 2025-02-24

### Added

- Add a support for Laravel 12

## [2.1.0] - 2024-07-22

### Added

- Add `TextArrayType`

## [2.0.0] - 2024-04-07

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

## [1.8.0] - 2022-08-17

### Added

- Add support PHP 8.1

## [1.7.0] - 2022-02-08

### Added

- Add ext-column types
- Geo Point (native PG `point` type)
- Geo Path (native PG `path` type)

## [1.6.1] - 2021-11-17

### Fixed

- `deleteAndReturn` on the service provider side

## [1.6.0] - 2021-11-15

### Added

- Add to `Builder` method `updateAndReturn`: Update records in the database and return columns of
  updated records
- Add to `Builder` method `deleteAndReturn`: Delete records in the database and return columns of
  deleted records

## [1.5.0] - 2021-11-04

### Added

- Add to `Blueprint` method `fromTable`: Create a table from another table and fills it data from
  the source-table
- Add to `Blueprint` method `fromSelect`: Create a table from select query

## [1.4.1] - 2021-11-04

### Changed

- Code style

## [1.4.0] - 2021-11-04

### Added

- Add to `Schema` method `dropIfExistsCascade`
- Add to `Blueprint` method `like`: Create a table from another table

## [1.3.1] - 2021-10-17

### Changed

- CI runs against a newer PostgreSQL

## [1.3.0] - 2021-10-16

### Added

- Add column options: `Compression`
- Add index: `ginIndex`

## [1.2.0] - 2021-10-16

### Added

- Add ext-column types - Array of UUID - Array of Integer

## [1.1.0] - 2021-09-28

### Added

- Add ext-column types - Date Range - IP Network - XML
- Add to `Schema` method `createViewOrReplace`
- Add helpers for `Extensions`: - `createExtension` - `createExtensionIfNotExists` -
  `dropExtensionIfExists`

## [1.0.1] - 2021-04-28

### Fixed

- GitHub Actions workflow

## [1.0.0] - 2021-04-28

### Changed

- Change minimal PHP version to `8.0`. Version of this package for php7 - see in branch `php7`

## [0.0.3] - 2021-02-11

### Added

- Add Bool `wheres` on Partial index

## [0.0.2] - 2021-01-27

### Added

- Unique partial indexes, the where-clause builder behind them, and the view definitions

## [0.0.1] - 2021-01-27

### Added

- Create the package

[unreleased]: https://github.com/efureev/laravel-support-db/compare/v5.0.5...HEAD

[5.0.5]: https://github.com/efureev/laravel-support-db/compare/v5.0.4...v5.0.5

[5.0.4]: https://github.com/efureev/laravel-support-db/compare/v5.0.3...v5.0.4

[5.0.3]: https://github.com/efureev/laravel-support-db/compare/v5.0.2...v5.0.3

[5.0.2]: https://github.com/efureev/laravel-support-db/compare/v5.0.1...v5.0.2

[5.0.1]: https://github.com/efureev/laravel-support-db/compare/v5.0.0...v5.0.1

[5.0.0]: https://github.com/efureev/laravel-support-db/compare/v4.0.0...v5.0.0

[4.0.0]: https://github.com/efureev/laravel-support-db/compare/v3.0.0...v4.0.0

[3.0.0]: https://github.com/efureev/laravel-support-db/compare/v2.2.1...v3.0.0

[2.1.0]: https://github.com/efureev/laravel-support-db/compare/v2.0.0...v2.1.0

[2.0.0]: https://github.com/efureev/laravel-support-db/compare/v1.11.0...v2.0.0

[1.11.0]: https://github.com/efureev/laravel-support-db/compare/v1.10.0...v1.11.0

[1.10.0]: https://github.com/efureev/laravel-support-db/compare/v1.9.0...v1.10.0

[1.9.0]: https://github.com/efureev/laravel-support-db/compare/v1.8.1...v1.9.0

[1.8.1]: https://github.com/efureev/laravel-support-db/compare/v1.8.0...v1.8.1

[1.8.0]: https://github.com/efureev/laravel-support-db/compare/v1.7.0...v1.8.0

[1.7.0]: https://github.com/efureev/laravel-support-db/compare/v1.6.1...v1.7.0

[1.6.0]: https://github.com/efureev/laravel-support-db/compare/v1.5.0...v1.6.0

[1.5.0]: https://github.com/efureev/laravel-support-db/compare/v1.4.1...v1.5.0

[1.4.0]: https://github.com/efureev/laravel-support-db/compare/v1.3.1...v1.4.0

[1.3.0]: https://github.com/efureev/laravel-support-db/compare/v1.2.0...v1.3.0

[1.2.0]: https://github.com/efureev/laravel-support-db/compare/v1.1.0...v1.2.0

[1.1.0]: https://github.com/efureev/laravel-support-db/compare/v1.0.1...v1.1.0

[1.0.0]: https://github.com/efureev/laravel-support-db/releases/tag/v1.0.0

[0.0.3]: https://github.com/efureev/laravel-support-db/compare/v0.0.2...v0.0.3

[0.0.2]: https://github.com/efureev/laravel-support-db/compare/v0.0.1...v0.0.2

[0.0.1]: https://github.com/efureev/laravel-support-db/releases/tag/v0.0.1

[keepachangelog]:https://keepachangelog.com/en/1.1.0/

[semver]:https://semver.org/spec/v2.0.0.html

[check-online]:https://dlaa.me/markdownlint
[2.2.1]: https://github.com/efureev/laravel-support-db/compare/v2.2.0...v2.2.1
[2.2.0]: https://github.com/efureev/laravel-support-db/compare/v2.1.0...v2.2.0
[1.6.1]: https://github.com/efureev/laravel-support-db/compare/v1.6.0...v1.6.1
[1.4.1]: https://github.com/efureev/laravel-support-db/compare/v1.4.0...v1.4.1
[1.3.1]: https://github.com/efureev/laravel-support-db/compare/v1.3.0...v1.3.1
[1.0.1]: https://github.com/efureev/laravel-support-db/compare/v1.0.0...v1.0.1
