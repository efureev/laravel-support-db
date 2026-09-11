<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit;

use LogicException;
use PDO;
use PHPUnit\Framework\TestCase;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Connection;

/**
 * Base class for tests that assert on generated SQL without touching a database.
 *
 * `Blueprint::toSql()` never reaches the PDO instance, so the connection is created with a
 * closure that would return `null` — no server, no Testbench, no container.
 */
abstract class UnitTestCase extends TestCase
{
    protected function connection(string $prefix = ''): Connection
    {
        $connection = new Connection(
            // Never invoked: `toSql()` does not reach the PDO instance.
            static fn(): PDO => throw new LogicException('A unit test must not open a connection.'),
            'testing',
            $prefix,
            [
                'driver'         => 'pgsql',
                'prefix_indexes' => true,
            ]
        );

        $connection->useDefaultSchemaGrammar();

        return $connection;
    }

    protected function blueprint(string $table, string $prefix = ''): Blueprint
    {
        return new Blueprint($this->connection($prefix), $table);
    }

    /**
     * Build a table and return the statements it compiles to.
     *
     * @return list<string>
     */
    protected function sqlFor(string $table, callable $definition, string $prefix = ''): array
    {
        $blueprint = $this->blueprint($table, $prefix);

        $definition($blueprint);

        return array_values($blueprint->toSql());
    }

    /**
     * Build a `create table` blueprint and return the statements it compiles to.
     *
     * @return list<string>
     */
    protected function sqlForCreate(string $table, callable $definition, string $prefix = ''): array
    {
        return $this->sqlFor(
            $table,
            static function (Blueprint $blueprint) use ($definition): void {
                $blueprint->create();
                $definition($blueprint);
            },
            $prefix
        );
    }

    /**
     * @param list<string> $statements
     */
    protected function assertNoStatementContains(string $needle, array $statements): void
    {
        foreach ($statements as $statement) {
            self::assertStringNotContainsString($needle, $statement);
        }
    }
}
