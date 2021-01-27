<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Schemas\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Fluent;
use Php\Support\Laravel\Schemas\Types\NumericType;
use Php\Support\Laravel\Schemas\Types\TsRangeType;

/**
 * Class ExtendedPostgresGrammar
 * @package Php\Support\Laravel
 */
class ExtendedPostgresGrammar extends PostgresGrammar
{
    /**
     * Create the column definition for a 'bit' type.
     *
     * @param \Illuminate\Support\Fluent $column
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

    protected function typeTsrange(Fluent $column): string
    {
        return TsRangeType::TYPE_NAME;
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
}
