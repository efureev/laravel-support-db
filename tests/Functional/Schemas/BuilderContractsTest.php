<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builder as PostgresBuilder;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ExtensionsAssertions;

/**
 * Contracts of the schema builder that only show up against a live server.
 */
class BuilderContractsTest extends AbstractTestCase
{
    use ExtensionsAssertions;

    protected function tearDown(): void
    {
        Schema::dropIfExistsCascade('test_table');

        parent::tearDown();
    }

    /**
     * `getViewDefinition()` must answer for a view that does not exist rather than indexing an
     * empty result set.
     */
    #[Test]
    public function theDefinitionOfAMissingViewIsEmpty(): void
    {
        self::assertFalse(Schema::hasView('no_such_view'));
        self::assertSame('', Schema::getViewDefinition('no_such_view'));
    }

    /**
     * A view lookup is one catalogue query and nothing else. Resolving the schema costs no
     * round-trip in Laravel 13 — it comes from config — so any extra query here would be a
     * regression in the lookup itself.
     */
    #[Test]
    public function aViewLookupCostsExactlyOneQuery(): void
    {
        /** @var PostgresBuilder $builder */
        $builder = DB::connection()->getSchemaBuilder();

        $queries = [];
        DB::listen(
            static function ($query) use (&$queries): void {
                $queries[] = $query->sql;
            }
        );

        $builder->hasView('no_such_view');
        self::assertCount(1, $queries);

        $builder->getViewDefinition('no_such_view');
        self::assertCount(2, $queries);

        foreach ($queries as $sql) {
            self::assertStringContainsString('pg_matviews', $sql, 'both catalogues in one query');
        }
    }

    /**
     * `dropIfExistsCascade()` exists to drop a table that other objects depend on. Without the
     * cascade PostgreSQL refuses.
     */
    #[Test]
    public function cascadeDropsDependentObjects(): void
    {
        Schema::create(
            'test_table',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
            }
        );
        Schema::createView('dependent_view', 'select id from test_table');

        self::assertTrue(Schema::hasView('dependent_view'));

        Schema::dropIfExistsCascade('test_table');

        self::assertFalse(Schema::hasTable('test_table'));
        self::assertFalse(Schema::hasView('dependent_view'), 'the dependent view goes with it');
    }

    #[Test]
    public function severalExtensionsAreDroppedInOneStatement(): void
    {
        Schema::createExtensionIfNotExists('uuid-ossp');
        Schema::createExtensionIfNotExists('tablefunc');
        $this->assertHasExtension('uuid-ossp');
        $this->assertHasExtension('tablefunc');

        Schema::dropExtensionIfExists('uuid-ossp', 'tablefunc');

        $this->assertHasNotExtension('uuid-ossp');
        $this->assertHasNotExtension('tablefunc');
    }

    #[Test]
    public function droppingNoExtensionIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name at least one extension');

        Schema::dropExtensionIfExists();
    }
}
