# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog][keepachangelog]
and this project adheres to [Semantic Versioning][semver].

Check MD [online][check-online].

## [unreleased]

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

[unreleased]: https://github.com/efureev/laravel-support-db/compare/v1.0.0...HEAD

[1.0.0]: https://github.com/efureev/laravel-support-db/releases/tag/v1.0.0

[0.0.3]: https://github.com/efureev/laravel-support-db/releases/tag/v0.0.3

[0.0.2]: https://github.com/efureev/laravel-support-db/releases/tag/v0.0.2

[0.0.1]: https://github.com/efureev/laravel-support-db/releases/tag/v0.0.1

[keepachangelog]:https://keepachangelog.com/en/1.1.0/

[semver]:https://semver.org/spec/v2.0.0.html

[check-online]:https://dlaa.me/markdownlint
