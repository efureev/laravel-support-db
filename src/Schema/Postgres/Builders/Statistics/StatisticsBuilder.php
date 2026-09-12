<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders\Statistics;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Fluent;
use InvalidArgumentException;

/**
 * An extended statistics object.
 *
 * The planner assumes columns are independent. When they are not — a city that implies its
 * country, a status that implies its type — its row estimates go wrong by orders of magnitude and
 * it picks the wrong plan. This tells it otherwise.
 *
 * @extends Fluent<string, mixed>
 */
class StatisticsBuilder extends Fluent
{
    /** What PostgreSQL can be asked to collect. */
    private const KINDS = [
        'ndistinct', 'dependencies', 'mcv',
    ];

    /**
     * The columns whose correlation the planner should learn.
     *
     * PostgreSQL requires at least two: a single column is what it already collects by itself.
     *
     * @param string|Expression ...$columns
     */
    public function on(string|Expression ...$columns): static
    {
        if (count($columns) < 2) {
            throw new InvalidArgumentException(
                'Extended statistics need at least two columns — a single column is what the '
                . 'planner already gathers on its own.'
            );
        }

        $this->attributes['columns'] = $columns;

        return $this;
    }

    /**
     * Which kinds to collect. All three by default.
     *
     * `ndistinct` counts distinct combinations, `dependencies` records that one column implies
     * another, and `mcv` lists the commonest combinations.
     */
    public function kinds(string ...$kinds): static
    {
        foreach ($kinds as $kind) {
            if (!in_array($kind, self::KINDS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown statistics kind [%s]; expected %s.',
                    $kind,
                    implode(', ', self::KINDS)
                ));
            }
        }

        $this->attributes['kinds'] = $kinds;

        return $this;
    }

    public function ifNotExists(bool $value = true): static
    {
        $this->attributes['ifNotExists'] = $value;

        return $this;
    }
}
