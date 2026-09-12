<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;
use PDO;
use LogicException;

/**
 * `compileReturns()` is concatenated straight onto a compiled UPDATE or DELETE, so an empty list
 * must produce nothing at all — ` returning` alone is a syntax error. Nothing tested it: the
 * functional tests only count the properties that come back, which passes with or without
 * quoting.
 */
final class ReturnsClauseTest extends UnitTestCase
{
    /** @param list<string> $columns */
    #[Test]
    #[DataProvider('columnLists')]
    public function theClauseIsBuiltFromTheColumnList(array $columns, string $expected): void
    {
        self::assertSame($expected, $this->queryGrammar()->compileReturns($columns));
    }

    /** @return iterable<string, array{list<string>, string}> */
    public static function columnLists(): iterable
    {
        yield 'empty list emits nothing' => [
            [],
            '',
        ];
        yield 'single column' => [
            ['id'],
            ' returning "id"',
        ];
        yield 'several columns' => [
            [
                'id',
                'name',
            ],
            ' returning "id","name"',
        ];
        yield 'duplicates collapse' => [
            [
                'id',
                'id',
            ],
            ' returning "id"',
        ];
        yield 'blank entries drop out' => [
            [
                'id',
                '',
            ],
            ' returning "id"',
        ];
        yield 'reserved word is quoted' => [
            ['order'],
            ' returning "order"',
        ];
        yield 'qualified column' => [
            ['tbl.col'],
            ' returning "tbl"."col"',
        ];
        yield 'star is not quoted' => [
            ['*'],
            ' returning *',
        ];
    }

    /**
     * The table prefix reaches a qualified column through `wrap()`, which is easy to lose.
     */
    #[Test]
    public function aQualifiedColumnPicksUpTheTablePrefix(): void
    {
        self::assertSame(
            ' returning "pref_tbl"."col"',
            $this->queryGrammar('pref_')->compileReturns(['tbl.col'])
        );
    }

    private function queryGrammar(string $prefix = ''): PostgresGrammar
    {
        $connection = new Connection(
            static fn(): PDO => throw new LogicException('A unit test must not open a connection.'),
            'testing',
            $prefix,
            ['driver' => 'pgsql']
        );

        /** @var PostgresGrammar $grammar */
        $grammar = $connection->getQueryGrammar();

        return $grammar;
    }
}
