<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

/**
 * PostgreSQL column types this package adds on top of the framework's.
 *
 * The value is the type name as it goes into DDL and as Laravel reports it back from
 * `Schema::getColumnType()`; `postgresType()` is what `information_schema.columns.data_type`
 * reports, which differs for array types.
 */
enum ColumnType: string
{
    case DateRange = 'daterange';
    case GeoPath   = 'path';
    case GeoPoint  = 'point';
    case IntArray  = 'integer[]';
    case IpNetwork = 'cidr';
    case Numeric   = 'numeric';
    case TextArray = 'text[]';
    case TsRange   = 'tsrange';
    case UuidArray = 'uuid[]';
    case Xml       = 'xml';

    /**
     * The type as Laravel's `Schema::getColumnType($table, $column, true)` reports it.
     */
    public function laravelType(): string
    {
        return $this->value;
    }

    /**
     * The type as `information_schema.columns.data_type` reports it.
     */
    public function postgresType(): string
    {
        return match ($this) {
            self::IntArray, self::TextArray, self::UuidArray => 'ARRAY',
            default => $this->value,
        };
    }
}
