<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\Types\NumericType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\TsRangeType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\XmlType;

trait GrammarTypes
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

}
