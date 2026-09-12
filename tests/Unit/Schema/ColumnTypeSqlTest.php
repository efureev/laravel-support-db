<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\ColumnType;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * The extended column types are the package's core product, yet the functional suite only ever
 * reads them back out of `information_schema`, which normalises hard: all three array types
 * report as `ARRAY`, and `numeric(10)` comes back as `numeric(10,0)`. Emitting a synonym — or the
 * wrong type entirely — would be invisible there. These assert what actually goes into the DDL.
 */
final class ColumnTypeSqlTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('columnTypes')]
    public function theEmittedTypeIsExact(string $method, string $expected): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table) use ($method): void {
                $table->{$method}('c');
            }
        );

        self::assertSame("create table \"t\" (\"c\" $expected not null)", $sql[0]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function columnTypes(): iterable
    {
        yield 'dateRange' => [
            'dateRange',
            'daterange',
        ];
        yield 'tsRange' => [
            'tsRange',
            'tsrange',
        ];
        yield 'timestampRange' => [
            'timestampRange',
            'tsrange',
        ];
        yield 'ipNetwork' => [
            'ipNetwork',
            'cidr',
        ];
        yield 'geoPoint' => [
            'geoPoint',
            'point',
        ];
        yield 'geoPath' => [
            'geoPath',
            'path',
        ];
        yield 'xml' => [
            'xml',
            'xml',
        ];
        yield 'uuidArray' => [
            'uuidArray',
            'uuid[]',
        ];
        yield 'textArray' => [
            'textArray',
            'text[]',
        ];
        yield 'intArray' => [
            'intArray',
            'integer[]',
        ];
        yield 'numeric' => [
            'numeric',
            'numeric',
        ];
    }

    #[Test]
    #[DataProvider('numericPrecisions')]
    public function numericCarriesItsPrecisionAndScale(?int $precision, ?int $scale, string $expected): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table) use ($precision, $scale): void {
                $table->numeric('c', $precision, $scale);
            }
        );

        self::assertSame("create table \"t\" (\"c\" $expected not null)", $sql[0]);
    }

    /** @return iterable<string, array{int|null, int|null, string}> */
    public static function numericPrecisions(): iterable
    {
        yield 'unconstrained' => [
            null,
            null,
            'numeric',
        ];
        yield 'precision only' => [
            10,
            null,
            'numeric(10)',
        ];
        yield 'precision and scale' => [
            10,
            2,
            'numeric(10, 2)',
        ];
        // PostgreSQL reads `numeric(10)` as `numeric(10,0)`, so dropping a zero scale is safe.
        yield 'zero scale collapses' => [
            10,
            0,
            'numeric(10)',
        ];
        // A precision of 0 is not valid in PostgreSQL; it degrades to unconstrained.
        yield 'zero precision degrades' => [
            0,
            null,
            'numeric',
        ];
    }

    #[Test]
    public function bitCarriesItsLength(): void
    {
        $sql = $this->sqlForCreate(
            't',
            static function (Blueprint $table): void {
                $table->bit('c', 4);
            }
        );

        self::assertSame('create table "t" ("c" bit(4) not null)', $sql[0]);
    }

    /**
     * `postgresType()` is what `information_schema.columns.data_type` reports, which is where the
     * functional assertions look; it diverges from the DDL spelling for array types only.
     */
    #[Test]
    #[DataProvider('catalogueTypes')]
    public function theCatalogueSpellingMatchesWhatPostgresReports(ColumnType $type, string $expected): void
    {
        self::assertSame($expected, $type->postgresType());
        self::assertSame($type->value, $type->laravelType());
    }

    /** @return iterable<string, array{ColumnType, string}> */
    public static function catalogueTypes(): iterable
    {
        yield 'uuid[]' => [
            ColumnType::UuidArray,
            'ARRAY',
        ];
        yield 'text[]' => [
            ColumnType::TextArray,
            'ARRAY',
        ];
        yield 'integer[]' => [
            ColumnType::IntArray,
            'ARRAY',
        ];
        yield 'xml' => [
            ColumnType::Xml,
            'xml',
        ];
        yield 'cidr' => [
            ColumnType::IpNetwork,
            'cidr',
        ];
        yield 'daterange' => [
            ColumnType::DateRange,
            'daterange',
        ];
        yield 'tsrange' => [
            ColumnType::TsRange,
            'tsrange',
        ];
        yield 'point' => [
            ColumnType::GeoPoint,
            'point',
        ];
        yield 'path' => [
            ColumnType::GeoPath,
            'path',
        ];
        yield 'numeric' => [
            ColumnType::Numeric,
            'numeric',
        ];
    }

    /**
     * Every case must be reachable from the blueprint; an orphaned case means a type nobody can
     * create, and a missing one means a `type*()` method with no enum entry.
     */
    #[Test]
    public function everyColumnTypeIsReachableFromTheBlueprint(): void
    {
        $emitted = [];

        foreach (
            [
                'dateRange',
                'tsRange',
                'ipNetwork',
                'geoPoint',
                'geoPath',
                'xml',
                'uuidArray',
                'textArray',
                'intArray',
                'numeric',
            ] as $method
        ) {
            $sql = $this->sqlForCreate('t', static fn(Blueprint $table) => $table->{$method}('c'));
            preg_match('/"c" (\S+)/', $sql[0], $m);
            $emitted[] = $m[1];
        }

        $declared = array_map(static fn(ColumnType $c): string => $c->value, ColumnType::cases());

        self::assertSame([], array_diff($declared, $emitted), 'every enum case must be emittable');
    }
}
