<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniquePartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\UniqueCompiler;
use Php\Support\Laravel\Database\Schema\Postgres\Types\NumericType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\TsRangeType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\XmlType;

class Grammar extends PostgresGrammar
{
    /**
     * Create the column definition for a 'bit' type.
     *
     * @param Fluent $column
     *
     * @return string
     */
    protected function typeBit(Fluent $column): string
    {
        return "bit({$column->length})";
    }

    protected function typeNumeric(Fluent $column): string
    {
        $type      = NumericType::TYPE_NAME;
        $precision = $column->get('precision');
        $scale     = $column->get('scale');

        if ($precision) {
            return "${type}({$precision}," . ($scale ? ", {$scale}" : '') . ')';
        }

        return $type;
    }

    protected function typeDateRange(Fluent $column): string
    {
        return 'daterange';
    }

    protected function typeUuidArray(Fluent $column): string
    {
        return 'uuid[]';
    }

    protected function typeIntArray(Fluent $column): string
    {
        return 'int[]';
    }

    protected function typeTsrange(Fluent $column): string
    {
        return TsRangeType::TYPE_NAME;
    }

    /**
     * Create the column definition for a xml type.
     */
    protected function typeXml(Fluent $column): string
    {
        return XmlType::TYPE_NAME;
    }

    /**
     * Create the column definition for an ip network type.
     */
    protected function typeIpNetwork(Fluent $column): string
    {
        return 'cidr';
    }

    public function compileCreateView(Blueprint $blueprint, Fluent $command): string
    {
        $materialize = $command->get('materialize') ? 'materialized' : '';

        return implode(
            ' ',
            array_filter(
                [
                    'create',
                    $materialize,
                    'view',
                    $this->wrapTable($command->get('view')),
                    'as',
                    $command->get('select'),
                ]
            )
        );
    }

    public function compileCreateViewOrReplace(Blueprint $blueprint, Fluent $command): string
    {
        $materialize = $command->get('materialize') ? 'materialized' : '';

        return implode(
            ' ',
            array_filter(
                [
                    'create or replace',
                    $materialize,
                    'view',
                    $this->wrapTable($command->get('view')),
                    'as',
                    $command->get('select'),
                ]
            )
        );
    }


    public function compileDropView(Blueprint $blueprint, Fluent $command): string
    {
        return 'drop view ' . $this->wrapTable($command->get('view'));
    }

    public function compileViewExists(): string
    {
        return 'select * from information_schema.views where table_schema = ? and table_name = ?';
    }

    public function compileViewDefinition(): string
    {
        return 'select view_definition from information_schema.views where table_schema = ? and table_name = ?';
    }

    public function compileUniquePartial(Blueprint $blueprint, UniqueBuilder $command): string
    {
        $constraints = $command->get('constraints');
        if ($constraints instanceof UniquePartialBuilder) {
            return UniqueCompiler::compile($this, $blueprint, $command, $constraints);
        }
        return $this->compileUnique($blueprint, $command);
    }

    public function naming(array $names)
    {
        return implode(', ', array_map([$this, 'wrap'], $names));
    }
}
