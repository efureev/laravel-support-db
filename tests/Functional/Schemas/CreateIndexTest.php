<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\IndexAssertions;
use Php\Support\Laravel\Database\Tests\Helpers\TableAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

class CreateIndexTest extends AbstractTestCase
{
    use TableAssertions;
    use IndexAssertions;

    #[Test]
    public function createIndexIfNotExists(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');

                if (!Schema::hasIndex('test_table', ['name'], 'unique')) {
                    $table->unique(['name']);
                }
            }
        );

        $this->seeTable('test_table');

        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                if (!Schema::hasIndex('test_table', ['name'], 'unique')) {
                    $table->unique(['name']);
                }
            }
        );

        $this->seeIndex('test_table_name_unique');
    }

    /**
     * These two used to have byte-identical bodies under group names promising a difference in
     * `search_path` that the bodies never made. The difference is real, so make it.
     */
    #[Test]
    #[Group('WithoutSchema')]
    public function anIndexLandsInTheDefaultSchema(): void
    {
        $this->createIndexDefinition();

        $this->assertRegExpIndex(
            'test_table_name_unique',
            '/CREATE UNIQUE INDEX test_table_name_unique ON (public\.)?test_table USING btree \(name\)/'
        );

        $index = $this->getIndexRow('test_table_name_unique');

        self::assertNotNull($index);
        self::assertSame('public', $index->schemaname);
    }

    /**
     * The index follows the session's `search_path`, not the `search_path` in the connection
     * config — the two can disagree, and PostgreSQL obeys the session.
     */
    #[Test]
    #[Group('WithSchema')]
    public function anIndexFollowsTheSessionSearchPath(): void
    {
        self::assertSame('public', config('database.connections.pgsql.search_path'));

        DB::statement('create schema test_schema');
        DB::statement('set search_path to test_schema');

        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->unique(['name']);
            }
        );

        $index = $this->getIndexRow('test_table_name_unique');

        self::assertNotNull($index, 'the index exists, in whichever schema it landed');
        self::assertSame('test_schema', $index->schemaname);
        self::assertStringContainsString('ON test_schema.test_table', $index->indexdef);
    }

    #[Test]
    public function createSpecifyIndex(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->string('name')
                    ->index('specify_index_name');
            }
        );

        $this->seeTable('test_table');

        $this->assertRegExpIndex(
            'specify_index_name',
            '/CREATE INDEX specify_index_name ON (public.)?test_table USING btree \(name\)/'
        );
    }

    private function createIndexDefinition(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');

                if (!Schema::hasIndex('test_table', ['name'])) {
                    $table->unique(['name']);
                }
            }
        );

        $this->seeTable('test_table');

        Schema::table(
            'test_table',
            static function (Blueprint $table) {
                if (!Schema::hasIndex('test_table', ['name'])) {
                    $table->unique(['name']);
                }
            }
        );

        $this->seeIndex('test_table_name_unique');
    }

    /**
     * The `$algorithm` argument of `partial()` used to be stored and never compiled (AUDIT.md D13).
     */
    #[Test]
    public function createPartialIndexWithAlgorithm(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->textArray('tags');
                $table->softDeletes();
                $table->partial('tags', 'test_table_tags_partial', 'gin')->whereNull('deleted_at');
            }
        );

        // The lookup is by index name, so the pattern only needs the parts under test:
        // the access method and the predicate.
        $this->assertRegExpIndex(
            'test_table_tags_partial',
            '/USING gin \(tags\) WHERE \(deleted_at IS NULL\)/'
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }
}
