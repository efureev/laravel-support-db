<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\IndexAssertions;

/**
 * `CREATE INDEX CONCURRENTLY` is refused inside a transaction block, so this case cannot use the
 * suite's transactional isolation and takes the wipe instead.
 */
class IndexModifiersTest extends AbstractTestCase
{
    use IndexAssertions;

    protected bool $transactional = false;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('code')->nullable();
                $table->softDeletes();
            }
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    /**
     * `pg_indexes.indexdef` does not record `CONCURRENTLY` — it is how the index was built, not
     * part of what it is. What the server proves here is that it accepted the statement; the
     * keyword itself is pinned by the unit tests, and by the transaction case below.
     */
    #[Test]
    public function aPartialIndexIsBuiltConcurrently(): void
    {
        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                $table->partial('code', 'test_table_code_partial')
                    ->whereNull('deleted_at')
                    ->online();
            }
        );

        $this->assertRegExpIndex(
            'test_table_code_partial',
            '/CREATE INDEX test_table_code_partial ON (public\.)?test_table USING btree \(code\) '
            . 'WHERE \(deleted_at IS NULL\)/'
        );
    }

    #[Test]
    public function aUniquePartialIndexIsBuiltConcurrently(): void
    {
        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                $table->uniquePartial('code', 'test_table_code_unique')
                    ->whereNull('deleted_at')
                    ->online();
            }
        );

        $this->assertRegExpIndex(
            'test_table_code_unique',
            '/CREATE UNIQUE INDEX test_table_code_unique ON (public\.)?test_table USING btree \(code\) '
            . 'WHERE \(deleted_at IS NULL\)/'
        );
    }

    /**
     * The statement is the assertion: PostgreSQL raises
     * `CREATE INDEX CONCURRENTLY cannot run inside a transaction block`, which is exactly why this
     * case opts out of the suite's transaction.
     */
    #[Test]
    public function concurrentlyIsRefusedInsideATransaction(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('cannot run inside a transaction block');

        Schema::getConnection()->transaction(
            static function () {
                Schema::table(
                    'test_table',
                    static function (Blueprint $table) {
                        $table->partial('code', 'test_table_code_partial')
                            ->whereNull('deleted_at')
                            ->online();
                    }
                );
            }
        );
    }

    /**
     * With `NULLS NOT DISTINCT` a second null in the indexed column is a duplicate; by default
     * nulls are distinct and any number of them fit.
     */
    #[Test]
    public function nullsNotDistinctAdmitsOnlyOneNull(): void
    {
        if ($this->serverVersion() < 150000) {
            self::markTestSkipped('NULLS NOT DISTINCT needs PostgreSQL 15.');
        }

        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                $table->uniquePartial('code', 'test_table_code_unique')
                    ->nullsNotDistinct()
                    ->whereNull('deleted_at');
            }
        );

        $this->assertRegExpIndex('test_table_code_unique', '/NULLS NOT DISTINCT/');

        $this->insertRow(null);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('duplicate key value violates unique constraint');

        $this->insertRow(null);
    }

    #[Test]
    public function withoutTheClauseNullsStayDistinct(): void
    {
        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                $table->uniquePartial('code', 'test_table_code_unique')->whereNull('deleted_at');
            }
        );

        $this->insertRow(null);
        $this->insertRow(null);

        self::assertSame(2, DB::table('test_table')->count());
    }

    private function insertRow(?string $code): void
    {
        DB::table('test_table')->insert(['code' => $code]);
    }
}
