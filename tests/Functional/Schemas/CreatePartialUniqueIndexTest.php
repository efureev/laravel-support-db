<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Closure;
use Generator;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Tests\Helpers\TableAssertions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CreatePartialUniqueIndexTest extends AbstractTestCase
{
    use TableAssertions;
    use IndexAssertions;

    #[Test]
    #[DataProvider('provideIndexes')]
    public function createPartialUnique(string $expected, Closure $callback): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) use ($callback) {
                $table->increments('id');
                $table->string('name');
                $table->string('code');
                $table->integer('phone');
                $table->boolean('enabled');
                $table->integer('icq');
                $table->softDeletes();

                $callback($table);
            }
        );

        $this->seeTable('test_table');
        $this->assertRegExpIndex('test_table_name_unique', '/' . self::getDummyIndex() . $expected . '/');

        Schema::table(
            'test_table',
            function (Blueprint $table) {
                if (!$this->existConstraintOnTable($table->getTable(), 'test_table_name_unique')) {
                    $table->dropUniquePartial(['name']);
                } else {
                    $table->dropUnique(['name']);
                }
            }
        );

        $this->notSeeIndex('test_table_name_unique');
    }


    public static function provideIndexes(): Generator
    {
        yield [
            '',
            fn(Blueprint $table) => $table->uniquePartial('name'),
        ];
        yield [
            ' WHERE \(deleted_at IS NULL\)',
            fn(Blueprint $table) => $table->uniquePartial('name')->whereNull('deleted_at'),
        ];
        yield [
            ' WHERE \(deleted_at IS NOT NULL\)',
            fn(Blueprint $table) => $table->uniquePartial('name')->whereNotNull('deleted_at'),
        ];
        yield [
            ' WHERE \(phone = 1234\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->where('phone', '=', 1234),
        ];
        yield [
            " WHERE \(\(code\)::text = 'test'::text\)",
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->where('code', '=', 'test'),
        ];
        yield [
            ' WHERE \(\(phone >= 1\) AND \(phone <= 2\)\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereBetween('phone', [1, 2]),
        ];
        yield [
            ' WHERE \(\(phone < 1\) OR \(phone > 2\)\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereNotBetween('phone', [1, 2]),
        ];
        yield [
            ' WHERE \(phone <> icq\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereColumn('phone', '<>', 'icq'),
        ];
        yield [
            ' WHERE \(\(phone = 1\) AND \(icq < 2\)\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereRaw('phone = ? and icq < ?', [1, 2]),
        ];
        yield [
            ' WHERE \(phone = ANY \(ARRAY\[1, 2, 4\]\)\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereIn('phone', [1, 2, 4]),
        ];
        yield [
            ' WHERE \(0 = 1\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereIn('phone', []),
        ];
        yield [
            ' WHERE \(phone <> ALL \(ARRAY\[1, 2, 4\]\)\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereNotIn('phone', [1, 2, 4]),
        ];
        yield [
            ' WHERE \(1 = 1\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereNotIn('phone', []),
        ];
        yield [
            ' WHERE \(enabled IS TRUE\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereBool('enabled', true),
        ];

        yield [
            ' WHERE \(enabled IS TRUE\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereTrue('enabled'),
        ];
        yield [
            ' WHERE \(enabled IS FALSE\)',
            fn(Blueprint $table) => $table->uniquePartial('name')
                ->whereFalse('enabled'),
        ];
    }


    protected static function getDummyIndex(): string
    {
        return 'CREATE UNIQUE INDEX test_table_name_unique ON (public.)?test_table USING btree \(name\)';
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }
}
