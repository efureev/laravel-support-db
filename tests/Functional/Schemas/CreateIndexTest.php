<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

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

    #[Group('WithSchema')]
    #[Test]
    public function createIndexWithSchema(): void
    {
        $this->createIndexDefinition();
        $this->assertSameIndex(
            'test_table_name_unique',
            'CREATE UNIQUE INDEX test_table_name_unique ON public.test_table USING btree (name)'
        );
    }

    #[Test]
    #[Group('WithoutSchema')]
    public function createIndexWithoutSchema(): void
    {
        $this->createIndexDefinition();
        $this->assertSameIndex(
            'test_table_name_unique',
            'CREATE UNIQUE INDEX test_table_name_unique ON public.test_table USING btree (name)'
        );
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

        $this->assertRegExpIndex(
            'test_table_tags_partial',
            '/CREATE INDEX test_table_tags_partial ON (public\.)?test_table USING gin \(tags\)'
            . ' WHERE \(deleted_at IS NULL\)/'
        );
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }
}
