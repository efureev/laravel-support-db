<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Regression tests for D9 — materialized views were create-only.
 */
final class ViewTest extends UnitTestCase
{
    #[Test]
    public function aPlainViewIsCreatedAndDropped(): void
    {
        self::assertSame(
            'create view "v" as select 1',
            $this->sqlFor('v', static fn(Blueprint $table) => $table->createView('v', 'select 1'))[0]
        );

        self::assertSame(
            'drop view "v"',
            $this->sqlFor('v', static fn(Blueprint $table) => $table->dropView('v'))[0]
        );
    }

    #[Test]
    public function aMaterializedViewIsDroppedWithTheMaterializedKeyword(): void
    {
        // `DROP VIEW` on a materialized view is an error in PostgreSQL.
        self::assertSame(
            'drop materialized view "v"',
            $this->sqlFor('v', static fn(Blueprint $table) => $table->dropView('v', true))[0]
        );
    }

    #[Test]
    public function dropViewIfExistsIsSupported(): void
    {
        self::assertSame(
            'drop view if exists "v"',
            $this->sqlFor('v', static fn(Blueprint $table) => $table->dropViewIfExists('v'))[0]
        );

        self::assertSame(
            'drop materialized view if exists "v"',
            $this->sqlFor('v', static fn(Blueprint $table) => $table->dropViewIfExists('v', true))[0]
        );
    }

    #[Test]
    public function orReplaceIsRejectedForMaterializedViews(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CREATE OR REPLACE for materialized views');

        $this->sqlFor('v', static fn(Blueprint $table) => $table->createViewOrReplace('v', 'select 1', true));
    }

    #[Test]
    public function orReplaceStillWorksForPlainViews(): void
    {
        self::assertSame(
            'create or replace view "v" as select 1',
            $this->sqlFor('v', static fn(Blueprint $table) => $table->createViewOrReplace('v', 'select 1'))[0]
        );
    }

    #[Test]
    public function viewLookupsCoverBothCatalogs(): void
    {
        $grammar = $this->connection()->getSchemaGrammar();

        foreach ([$grammar->compileViewExists(), $grammar->compileViewDefinition()] as $sql) {
            self::assertStringContainsString('pg_views', $sql);
            self::assertStringContainsString('pg_matviews', $sql);
            self::assertSame(4, substr_count($sql, '?'), 'schema and name are bound for each catalog');
        }
    }

    #[Test]
    public function materializedViewsCanBeRefreshed(): void
    {
        $grammar = $this->connection()->getSchemaGrammar();

        self::assertSame('refresh materialized view "v"', $grammar->compileRefreshMaterializedView('v'));
        self::assertSame(
            'refresh materialized view concurrently "v"',
            $grammar->compileRefreshMaterializedView('v', true)
        );
    }
}
