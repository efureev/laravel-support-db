<?php

namespace Php\Support\Laravel\Database\Query\Grammars;

use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;

class PostgresGrammar extends BasePostgresGrammar
{
    public function compileReturns(array $cols): string
    {
        $cols = array_unique(array_filter($cols));

        $returns = implode(',', array_map(fn($item) => $this->wrap($item), $cols));

        return $returns ? " RETURNING $returns" : '';
    }
}
