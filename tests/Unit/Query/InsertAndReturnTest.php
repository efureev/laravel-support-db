<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Query;

use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Query\Builder;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * `insertGetId()` returns one key of one row. This returns whatever columns are named, for every
 * row, which is the only way to read back what the database generated for a batch.
 */
final class InsertAndReturnTest extends UnitTestCase
{
    #[Test]
    public function aSingleRowIsCompiledAsABatchOfOne(): void
    {
        self::assertSame(
            'insert into "orders" ("total") values (?) returning "id"',
            $this->insertSql(['total' => 10], 'id')
        );
    }

    #[Test]
    public function severalRowsShareOnePlaceholderList(): void
    {
        self::assertSame(
            'insert into "orders" ("total") values (?), (?) returning "id","total"',
            $this->insertSql([['total' => 10], ['total' => 20]], 'id', 'total')
        );
    }

    /**
     * The framework sorts each row's keys so every row lists its columns in the same order; a
     * batch whose rows disagree would otherwise bind values to the wrong columns.
     */
    #[Test]
    public function rowKeysAreSortedSoTheBatchLinesUp(): void
    {
        self::assertSame(
            'insert into "orders" ("state", "total") values (?, ?), (?, ?) returning "id"',
            $this->insertSql([['total' => 10, 'state' => 'new'], ['state' => 'paid', 'total' => 20]], 'id')
        );
    }

    #[Test]
    public function namingNoColumnEmitsNoReturningClause(): void
    {
        self::assertSame(
            'insert into "orders" ("total") values (?)',
            $this->insertSql(['total' => 10])
        );
    }

    #[Test]
    public function starReturnsTheWholeRow(): void
    {
        self::assertSame(
            'insert into "orders" ("total") values (?) returning *',
            $this->insertSql(['total' => 10], '*')
        );
    }

    #[Test]
    public function theTablePrefixReachesTheStatement(): void
    {
        self::assertSame(
            'insert into "pref_orders" ("total") values (?) returning "id"',
            $this->compile(['total' => 10], ['id'], 'pref_')
        );
    }

    /** @param array<array-key, mixed> $values */
    private function insertSql(array $values, string ...$columns): string
    {
        return $this->compile($values, $columns, '');
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string> $columns
     */
    private function compile(array $values, array $columns, string $prefix): string
    {
        $connection = new class (
            static fn(): PDO => throw new LogicException('A unit test must not open a connection.'),
            'testing',
            $prefix,
            ['driver' => 'pgsql']
        ) extends Connection {
            public string $seen = '';

            /** @param array<array-key, mixed> $bindings */
            #[\Override]
            public function insertAndReturn(string $query, array $bindings = []): array
            {
                $this->seen = $query;

                return [];
            }
        };

        /** @var Builder $query */
        $query = $connection->query();
        $query->from('orders')->insertAndReturn($values, ...$columns);

        return $connection->seen;
    }
}
