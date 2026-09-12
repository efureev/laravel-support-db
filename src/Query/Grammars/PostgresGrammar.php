<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Query\Grammars;

use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;

class PostgresGrammar extends BasePostgresGrammar
{
    /** @param array<array-key, string> $cols */
    public function compileReturns(array $cols): string
    {
        $cols = array_unique(array_filter($cols));

        $returns = implode(',', array_map($this->wrap(...), $cols));

        return $returns ? " returning $returns" : '';
    }
}
