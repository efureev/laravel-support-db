<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;
use PDO;
use Php\Support\Laravel\Database\Schema\Postgres\Types\NumericType;
use Php\Support\Laravel\Database\Schema\Postgres\Types\TsRangeType;

class Connection extends BasePostgresConnection
{
    private array $initialTypes = [
        TsRangeType::TYPE_NAME => TsRangeType::class,
        NumericType::TYPE_NAME => NumericType::class,
    ];

    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new Grammar());
    }


    public function getSchemaBuilder()
    {
        if ($this->schemaGrammar === null) {
            $this->useDefaultSchemaGrammar();
        }
        return new Builder($this);
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

                switch (true) {
                    case is_bool($value):
                        $dataType = PDO::PARAM_BOOL;
                        break;

                    case $value === null:
                        $dataType = PDO::PARAM_NULL;
                        break;

                    default:
                        $dataType = PDO::PARAM_STR;
                }

                $statement->bindValue($parameter, $value, $dataType);
            }
        } else {
            parent::bindValues($statement, $bindings);
        }
    }

    private function registerInitialTypes(): void
    {
        $builder = $this->getSchemaBuilder();
        foreach ($this->initialTypes as $type => $typeClass) {
            $builder->registerCustomDoctrineType($typeClass, $type, $type);
        }
    }
}
