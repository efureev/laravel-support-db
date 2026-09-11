<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Illuminate\Support\Fluent;
use Php\Support\Laravel\Database\Schema\Postgres\ColumnType;

trait GrammarTypes
{
    /*
     * Laravel dispatches these by name from `Grammar::getType(Fluent $column)`, so the parameter
     * must stay as wide as the framework's — narrowing it to the package's ColumnDefinition made
     * any blueprint built by a custom resolver a fatal TypeError.
     */

    /**
     * Create the column definition for a 'bit' type.
     *
     * @param Fluent<string, mixed> $column
     */
    protected function typeBit(Fluent $column): string
    {
        return "bit({$column->value('length')})";
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeNumeric(Fluent $column): string
    {
        $type      = ColumnType::Numeric->value;
        $precision = $column->get('precision');
        $scale     = $column->get('scale');

        if ($precision) {
            return "$type($precision" . ($scale ? ", $scale" : '') . ')';
        }

        return $type;
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeDateRange(Fluent $column): string
    {
        return ColumnType::DateRange->value;
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeUuidArray(Fluent $column): string
    {
        return ColumnType::UuidArray->value;
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeTextArray(Fluent $column): string
    {
        return ColumnType::TextArray->value;
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeIntArray(Fluent $column): string
    {
        return ColumnType::IntArray->value;
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeTsrange(Fluent $column): string
    {
        return ColumnType::TsRange->value;
    }

    /**
     * Create the column definition for a xml type.
     *
     * @param Fluent<string, mixed> $column
     */
    protected function typeXml(Fluent $column): string
    {
        return ColumnType::Xml->value;
    }

    /**
     * Create the column definition for an ip network type.
     *
     * @param Fluent<string, mixed> $column
     */
    protected function typeIpNetwork(Fluent $column): string
    {
        return ColumnType::IpNetwork->value;
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeGeoPoint(Fluent $column): string
    {
        return ColumnType::GeoPoint->value;
    }

    /** @param Fluent<string, mixed> $column */
    protected function typeGeoPath(Fluent $column): string
    {
        return ColumnType::GeoPath->value;
    }
}
