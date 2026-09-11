<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Compilers;

use Illuminate\Support\Fluent;
use InvalidArgumentException;

trait CompilesIndexAlgorithm
{
    /**
     * Build the `using <method>` clause of a CREATE INDEX statement.
     *
     * The attribute is set either by the third argument of `partial()` / `uniquePartial()` or by
     * a fluent `->algorithm()` call — both land in the same Fluent attribute.
     *
     * Placement matches the framework's own `PostgresGrammar::compileIndex()`: right after the
     * table name and before the column list, unquoted.
     */
    protected static function algorithmClause(Fluent $fluent): string
    {
        $algorithm = $fluent->get('algorithm');

        if ($algorithm === null || $algorithm === '' || $algorithm === false) {
            return '';
        }

        $algorithm = (string)$algorithm;

        // Interpolated into DDL, so it must be a bare identifier.
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $algorithm) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid index algorithm [%s]. Expected an identifier, e.g. btree or gin.', $algorithm)
            );
        }

        return " using $algorithm";
    }
}
