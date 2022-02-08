<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use PDO;
use Php\Support\Laravel\Database\Query\Builder as QueryBuilder;
use Php\Support\Laravel\Database\Query\Grammars\PostgresGrammar as QueryPostgresGrammar;
use Php\Support\Laravel\Database\Schema\Postgres\Types\DateRangeType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\GeoPathType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\GeoPointType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\IntArrayType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\IpNetworkType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\NumericType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\TsRangeType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\UuidArrayType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\XmlType;

class Connection extends BasePostgresConnection
{
    private array $initialTypes = [
        DateRangeType::TYPE_NAME => DateRangeType::class,
        GeoPathType::TYPE_NAME   => GeoPathType::class,
        GeoPointType::TYPE_NAME  => GeoPointType::class,
        IntArrayType::TYPE_NAME  => IntArrayType::class,
        IpNetworkType::TYPE_NAME => IpNetworkType::class,
        NumericType::TYPE_NAME   => NumericType::class,
        TsRangeType::TYPE_NAME   => TsRangeType::class,
        UuidArrayType::TYPE_NAME => UuidArrayType::class,
        XmlType::TYPE_NAME       => XmlType::class,
    ];

    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix((new Grammar())->addModifier('Compression'));
    }


    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }
        return new Builder($this);
    }

    public function query()
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    protected function getDefaultQueryGrammar()
    {
        return new QueryPostgresGrammar();
    }

    public function useDefaultPostProcessor(): void
    {
        parent::useDefaultPostProcessor();

        $this->registerInitialTypes();
    }

    public function bindValues($statement, $bindings): void
    {
        if ($this->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES)) {
            foreach ($bindings as $key => $value) {
                $parameter = is_string($key) ? $key : $key + 1;

                $dataType = match (true) {
                    is_bool($value) => PDO::PARAM_BOOL,
                    $value === null => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                };

                $statement->bindValue($parameter, $value, $dataType);
            }
        } else {
            parent::bindValues($statement, $bindings);
        }
    }

    public function registerInitialTypes(): void
    {
        $builder = $this->getSchemaBuilder();
        foreach ($this->initialTypes as $type => $typeClass) {
            $builder->registerCustomDoctrineType($typeClass, $type, $type);
        }
    }

    public function updateAndReturn($query, $bindings = []): array
    {
        return $this->affectingStatementArray($query, $bindings);
    }

    public function deleteAndReturn($query, $bindings = []): array
    {
        return $this->affectingStatementArray($query, $bindings);
    }

    public function affectingStatementArray($query, $bindings = []): array
    {
        return $this->run(
            $query,
            $bindings,
            function ($query, $bindings) {
                if ($this->pretending()) {
                    return [];
                }

                $statement = $this->getPdo()->prepare($query);

                $this->bindValues($statement, $this->prepareBindings($bindings));

                $statement->execute();

                $this->recordsHaveBeenModified(
                    ($list = $this->associateStatement($statement))
                );

                return $list;
            }
        );
    }

    public function associateStatement($statement): array
    {
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
