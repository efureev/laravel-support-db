<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Php\Support\Laravel\Database\Schema\Definitions\ColumnDefinition;
use Php\Support\Laravel\Database\Schema\Postgres\ColumnType;

trait GrammarTypes
{
    /**
     * Create the column definition for a 'bit' type.
     */
    protected function typeBit(ColumnDefinition $column): string
    {
        return "bit({$column->value('length')})";
    }

    protected function typeNumeric(ColumnDefinition $column): string
    {
        $type      = ColumnType::Numeric->value;
        $precision = $column->get('precision');
        $scale     = $column->get('scale');

        if ($precision) {
            return "$type($precision" . ($scale ? ", $scale" : '') . ')';
        }

        return $type;
    }

    protected function typeDateRange(ColumnDefinition $column): string
    {
        return ColumnType::DateRange->value;
    }

    protected function typeUuidArray(ColumnDefinition $column): string
    {
        return ColumnType::UuidArray->value;
    }

    protected function typeTextArray(ColumnDefinition $column): string
    {
        return ColumnType::TextArray->value;
    }

    protected function typeIntArray(ColumnDefinition $column): string
    {
        return ColumnType::IntArray->value;
    }

    protected function typeTsrange(ColumnDefinition $column): string
    {
        return ColumnType::TsRange->value;
    }

    /**
     * Create the column definition for a xml type.
     */
    protected function typeXml(ColumnDefinition $column): string
    {
        return ColumnType::Xml->value;
    }

    /**
     * Create the column definition for an ip network type.
     */
    protected function typeIpNetwork(ColumnDefinition $column): string
    {
        return ColumnType::IpNetwork->value;
    }

    protected function typeGeoPoint(ColumnDefinition $column): string
    {
        return ColumnType::GeoPoint->value;
    }

    protected function typeGeoPath(ColumnDefinition $column): string
    {
        return ColumnType::GeoPath->value;
    }
}
