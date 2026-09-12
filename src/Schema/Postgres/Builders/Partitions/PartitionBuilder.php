<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Schema\Postgres\Builders\Partitions;

use Illuminate\Support\Fluent;
use InvalidArgumentException;

/**
 * The bounds of one partition.
 *
 * A partition belongs to exactly one strategy, so the four ways of naming its bounds are mutually
 * exclusive: a second call is refused rather than quietly replacing the first.
 *
 * @extends Fluent<string, mixed>
 */
class PartitionBuilder extends Fluent
{
    /** `for values from (…) to (…)` — a range partition. The lower bound is inclusive. */
    public function fromTo(string|int|float $from, string|int|float $to): static
    {
        return $this->bounds('range', compact('from', 'to'));
    }

    /**
     * `for values in (…)` — the values a list partition holds.
     *
     * @param list<string|int|float> $values
     */
    public function in(array $values): static
    {
        if ($values === []) {
            throw new InvalidArgumentException('A list partition needs at least one value.');
        }

        return $this->bounds('list', compact('values'));
    }

    /** `for values with (modulus …, remainder …)` — one slice of a hash-partitioned table. */
    public function hash(int $modulus, int $remainder): static
    {
        if ($modulus < 1) {
            throw new InvalidArgumentException('A hash partition needs a modulus of at least 1.');
        }

        if ($remainder < 0 || $remainder >= $modulus) {
            throw new InvalidArgumentException(
                sprintf('A remainder must be between 0 and %d, %d given.', $modulus - 1, $remainder)
            );
        }

        return $this->bounds('hash', compact('modulus', 'remainder'));
    }

    /** `default` — everything the other partitions do not take. */
    public function asDefault(): static
    {
        return $this->bounds('default', []);
    }

    /** @param array<string, mixed> $values */
    private function bounds(string $strategy, array $values): static
    {
        if (isset($this->attributes['strategy'])) {
            throw new InvalidArgumentException(
                sprintf(
                    'This partition already has %s bounds; a partition belongs to one strategy.',
                    $this->attributes['strategy']
                )
            );
        }

        $this->attributes['strategy'] = $strategy;
        $this->attributes['bounds'] = $values;

        return $this;
    }
}
