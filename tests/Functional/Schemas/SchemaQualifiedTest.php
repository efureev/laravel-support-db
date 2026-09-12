<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ViewAssertions;

/**
 * `createView()` and `dropView()` have always honoured a `schema.view` reference, because
 * `wrapTable()` splits on the dot. The lookups did not: they bound the current schema and the
 * whole dotted string as the name, so you could create a view in another schema through this
 * builder and then never find it again.
 */
class SchemaQualifiedTest extends AbstractTestCase
{
    use ViewAssertions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('create schema reporting');
    }

    #[Test]
    public function aViewInAnotherSchemaIsFound(): void
    {
        Schema::createView('reporting.sales', 'select 1 as total');

        $this->seeView('reporting.sales');
        self::assertStringContainsString('select 1 as total', $this->viewDefinition('reporting.sales'));
    }

    #[Test]
    public function aMaterializedViewInAnotherSchemaIsFound(): void
    {
        Schema::createView('reporting.totals', 'select 1 as total', true);

        $this->seeView('reporting.totals');
        self::assertStringContainsString('select 1 as total', $this->viewDefinition('reporting.totals'));
    }

    /**
     * The reference is the whole answer: an unqualified name must not reach into another schema,
     * or `hasView()` would be answering about a view the caller did not name.
     */
    #[Test]
    public function theSchemaIsPartOfTheQuestion(): void
    {
        Schema::createView('reporting.sales', 'select 1 as total');

        $this->notSeeView('sales');
        self::assertSame('', Schema::getViewDefinition('sales'));
    }

    #[Test]
    public function aViewInAnotherSchemaCanBeDropped(): void
    {
        Schema::createView('reporting.sales', 'select 1 as total');
        $this->seeView('reporting.sales');

        Schema::dropView('reporting.sales');

        $this->notSeeView('reporting.sales');
    }

    /**
     * Three parts would be `database.schema.view`, which no single connection can reach.
     */
    #[Test]
    public function aThreePartReferenceIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('three-part references');

        Schema::hasView('some_db.reporting.sales');
    }

    private function viewDefinition(string $view): string
    {
        return strtolower(trim(Schema::getViewDefinition($view)));
    }
}
