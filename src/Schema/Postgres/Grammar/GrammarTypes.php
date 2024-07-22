<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Grammar;

use Php\Support\Laravel\Database\Schema\Definitions\ColumnDefinition;
use Php\Support\Laravel\Database\Schema\Postgres\Types\DateRangeType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\GeoPathType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\GeoPointType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\IntArrayType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\IpNetworkType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\NumericType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\TextArrayType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\TsRangeType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\UuidArrayType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\XmlType;

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
        $type      = NumericType::TYPE_NAME;
        $precision = $column->get('precision');
        $scale     = $column->get('scale');

        if ($precision) {
            return "$type($precision" . ($scale ? ", $scale" : '') . ')';
        }

        return $type;
    }

    protected function typeDateRange(ColumnDefinition $column): string
    {
        return DateRangeType::TYPE_NAME;
    }

    protected function typeUuidArray(ColumnDefinition $column): string
    {
        return UuidArrayType::TYPE_NAME;
    }

    protected function typeTextArray(ColumnDefinition $column): string
    {
        return TextArrayType::TYPE_NAME;
    }

    protected function typeIntArray(ColumnDefinition $column): string
    {
        return IntArrayType::TYPE_NAME;
    }

    protected function typeTsrange(ColumnDefinition $column): string
    {
        return TsRangeType::TYPE_NAME;
    }

    /**
     * Create the column definition for a xml type.
     */
    protected function typeXml(ColumnDefinition $column): string
    {
        return XmlType::TYPE_NAME;
    }

    /**
     * Create the column definition for an ip network type.
     */
    protected function typeIpNetwork(ColumnDefinition $column): string
    {
        return IpNetworkType::TYPE_NAME;
    }

    protected function typeGeoPoint(ColumnDefinition $column): string
    {
        return GeoPointType::TYPE_NAME;
    }

    protected function typeGeoPath(ColumnDefinition $column): string
    {
        return GeoPathType::TYPE_NAME;
    }
}
