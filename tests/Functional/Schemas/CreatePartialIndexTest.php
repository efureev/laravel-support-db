<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Closure;
use Generator;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Schema\Helpers\TableAssertions;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

class CreatePartialIndexTest extends AbstractTestCase
{
    use TableAssertions;
    use IndexAssertions;

    /**
     * @test
     */
//    public function createPartialOne(): void
//    {
//        Schema::create(
//            'test_table',
//            static function (Blueprint $table) {
//                $table->increments('id');
//                $table->string('name');
//                $table->string('code');
//                $table->integer('phone');
//                $table->boolean('enabled');
//                $table->integer('icq');
//                $table->softDeletes();
//
//                $table->partial('name'); //->whereNull('deleted_at');
//            }
//        );
//
//        $this->seeTable('test_table');
////        $this->assertRegExpIndex('test_table_name_partial', '/' . self::getDummyIndex() . ' WHERE \(deleted_at IS NULL\)/');
//        $this->assertRegExpIndex('test_table_name_partial', '/' . self::getDummyIndex() . '/');
//    }


    /**
     * @test
     * @dataProvider provideIndexes
     *
     * @param string $expected
     * @param Closure $callback
     */
    public function createPartial(string $expected, Closure $callback): void
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
        $this->assertRegExpIndex('test_table_name_partial', '/' . self::getDummyIndex() . $expected . '/');

        Schema::table(
            'test_table',
            function (Blueprint $table) {
                if (!$this->existConstraintOnTable($table->getTable(), 'test_table_name_partial')) {
                    $table->dropPartial(['name']);
                } else {
                    $table->dropUnique(['name']);
                }
            }
        );

        $this->notSeeIndex('test_table_name_unique');
    }

    public function provideIndexes(): Generator
    {
        yield [
            '',
            fn(Blueprint $table) => $table->partial('name'),
        ];
        yield [
            ' WHERE \(deleted_at IS NULL\)',
            fn(Blueprint $table) => $table->partial('name')->whereNull('deleted_at'),
        ];
        yield [
            ' WHERE \(deleted_at IS NOT NULL\)',
            fn(Blueprint $table) => $table->partial('name')->whereNotNull('deleted_at'),
        ];
        yield [
            ' WHERE \\(\\(deleted_at IS NOT NULL\\) AND \\(phone = 1234\\)\\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereNotNull('deleted_at')
                ->where('phone', '=', '1234'),
        ];
        yield [
            ' WHERE \(phone = 1234\)',
            fn(Blueprint $table) => $table->partial('name')
                ->where('phone', '=', '1234'),
        ];
        yield [
            " WHERE \(\(code\)::text = 'test'::text\)",
            fn(Blueprint $table) => $table->partial('name')
                ->where('code', '=', 'test'),
        ];
        yield [
            ' WHERE \(\(phone >= 1\) AND \(phone <= 2\)\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereBetween('phone', [1, 2]),
        ];
        yield [
            ' WHERE \(\(phone < 1\) OR \(phone > 2\)\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereNotBetween('phone', [1, 2]),
        ];
        yield [
            ' WHERE \(phone <> icq\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereColumn('phone', '<>', 'icq'),
        ];
        yield [
            ' WHERE \(\(phone = 1\) AND \(icq < 2\)\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereRaw('phone = ? and icq < ?', [1, 2]),
        ];
        yield [
            ' WHERE \(phone = ANY \(ARRAY\[1, 2, 4\]\)\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereIn('phone', [1, 2, 4]),
        ];
        yield [
            ' WHERE \(0 = 1\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereIn('phone', []),
        ];
        yield [
            ' WHERE \(phone <> ALL \(ARRAY\[1, 2, 4\]\)\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereNotIn('phone', [1, 2, 4]),
        ];
        yield [
            ' WHERE \(1 = 1\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereNotIn('phone', []),
        ];
        yield [
            ' WHERE \(enabled IS TRUE\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereBool('enabled', true),
        ];

        yield [
            ' WHERE \(enabled IS TRUE\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereTrue('enabled'),
        ];
        yield [
            ' WHERE \(enabled IS FALSE\)',
            fn(Blueprint $table) => $table->partial('name')
                ->whereFalse('enabled'),
        ];
    }


    protected static function getDummyIndex(): string
    {
        return 'CREATE INDEX test_table_name_partial ON (public.)?test_table USING btree \(name\)';
    }


    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }
}
