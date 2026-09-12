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


    /** @param array<array-key, string> $names */
    public function naming(array $names): string
    {
        return implode(', ', array_map($this->wrap(...), $names));
    }

    /**
     * Prepend a column modifier to the ones inherited from the parent grammar.
     *
     * `$this->modifiers` is a list, so the union operator (`[$value] + $list`) would overwrite
     * the element at key 0 instead of shifting it — silently dropping `Collate`.
     */
    public function addModifier(string $value): static
    {
        if (!in_array($value, $this->modifiers, true)) {
            array_unshift($this->modifiers, $value);
        }

        return $this;
    }
}
