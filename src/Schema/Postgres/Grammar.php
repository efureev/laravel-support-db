<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar\CompressionModifier;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar\GrammarIndexes;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar\GrammarTable;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar\GrammarTypes;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar\GrammarViews;

class Grammar extends PostgresGrammar
{
    use GrammarTable;
    use GrammarTypes;
    use GrammarIndexes;
    use GrammarViews;
    use CompressionModifier;


    public function naming(array $names): string
    {
        return implode(', ', array_map([$this, 'wrap'], $names));
    }

    public function addModifier(string $value): static
    {
        $this->modifiers = [$value] + $this->modifiers;

        return $this;
    }
}
