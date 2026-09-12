<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;
use ReflectionMethod;

/**
 * Every predicate takes a trailing `$boolean`; these are its `or` spellings, so a disjunction
 * reads as one rather than ending in a stray `'or'` argument.
 */
final class OrWhereTest extends UnitTestCase
{
    /** @return iterable<string, array{callable(PartialBuilder): mixed, string}> */
    public static function disjunctions(): iterable
    {
        yield 'orWhere' => [
            static fn(PartialBuilder $i) => $i->orWhere('size', '>', 10),
            '("size" > 10)',
        ];
        yield 'orWhereRaw' => [
            static fn(PartialBuilder $i) => $i->orWhereRaw('size > ?', [10]),
            '(size > 10)',
        ];
        yield 'orWhereBool' => [
            static fn(PartialBuilder $i) => $i->orWhereBool('archived', false),
            '("archived" is false)',
        ];
        yield 'orWhereTrue' => [
            static fn(PartialBuilder $i) => $i->orWhereTrue('archived'),
            '("archived" is true)',
        ];
        yield 'orWhereFalse' => [
            static fn(PartialBuilder $i) => $i->orWhereFalse('archived'),
            '("archived" is false)',
        ];
        yield 'orWhereColumn' => [
            static fn(PartialBuilder $i) => $i->orWhereColumn('created_at', '<', 'updated_at'),
            '("created_at" < "updated_at")',
        ];
        yield 'orWhereIn' => [
            static fn(PartialBuilder $i) => $i->orWhereIn('state', ['a', 'b']),
            '("state" in (\'a\',\'b\'))',
        ];
        yield 'orWhereNotIn' => [
            static fn(PartialBuilder $i) => $i->orWhereNotIn('state', ['a']),
            '("state" not in (\'a\'))',
        ];
        yield 'orWhereNull' => [
            static fn(PartialBuilder $i) => $i->orWhereNull('deleted_at'),
            '("deleted_at" is null)',
        ];
        yield 'orWhereNotNull' => [
            static fn(PartialBuilder $i) => $i->orWhereNotNull('deleted_at'),
            '("deleted_at" is not null)',
        ];
        yield 'orWhereBetween' => [
            static fn(PartialBuilder $i) => $i->orWhereBetween('size', [1, 10]),
            '("size" between 1 and 10)',
        ];
        yield 'orWhereNotBetween' => [
            static fn(PartialBuilder $i) => $i->orWhereNotBetween('size', [1, 10]),
            '("size" not between 1 and 10)',
        ];
    }

    #[Test]
    #[DataProvider('disjunctions')]
    public function eachOrPredicateJoinsWithOr(callable $addPredicate, string $expected): void
    {
        $sql = $this->sqlFor(
            'docs',
            static function (Blueprint $table) use ($addPredicate): void {
                $index = $table->partial('code', 'ix')->whereTrue('published');
                $addPredicate($index);
            }
        );

        self::assertSame(
            'create index "ix" on "docs" ("code") where ("published" is true) or ' . $expected,
            $sql[0]
        );
    }

    /**
     * The `or` spelling must be the only difference: same predicate, same rendering.
     *
     * @param callable(PartialBuilder): mixed $addPredicate
     */
    #[Test]
    #[DataProvider('disjunctions')]
    public function theAndSpellingRendersTheSamePredicate(callable $addPredicate, string $expected): void
    {
        $sql = $this->sqlFor(
            'docs',
            static function (Blueprint $table) use ($addPredicate): void {
                $index = $table->partial('code', 'ix')->whereTrue('published');
                $addPredicate($index);
            }
        );

        self::assertStringContainsString($expected, $sql[0]);
    }

    /**
     * A leading `or` is stripped, the same way the framework strips a leading boolean: an index
     * whose first predicate is a disjunction must not compile to `where or (...)`.
     */
    #[Test]
    public function aLeadingOrIsRemoved(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->partial('code', 'ix')->orWhereNull('deleted_at')
        );

        self::assertSame(
            'create index "ix" on "docs" ("code") where ("deleted_at" is null)',
            $sql[0]
        );
    }

    #[Test]
    public function orAndAndMixInOrder(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $t) => $t->partial('code', 'ix')
                ->whereNull('deleted_at')
                ->orWhereTrue('archived')
                ->whereFalse('draft')
        );

        self::assertSame(
            'create index "ix" on "docs" ("code") where ("deleted_at" is null) '
            . 'or ("archived" is true) and ("draft" is false)',
            $sql[0]
        );
    }

    /**
     * Every predicate that takes a `$boolean` needs an `or` spelling, or the set is arbitrary.
     */
    #[Test]
    public function everyPredicateHasAnOrSpelling(): void
    {
        $missing = [];

        foreach (get_class_methods(PartialBuilder::class) as $method) {
            if (!str_starts_with($method, 'where')) {
                continue;
            }

            $takesBoolean = array_filter(
                (new ReflectionMethod(PartialBuilder::class, $method))->getParameters(),
                static fn(\ReflectionParameter $p): bool => $p->getName() === 'boolean'
            );

            if ($takesBoolean === []) {
                continue;
            }

            if (!method_exists(PartialBuilder::class, 'or' . ucfirst($method))) {
                $missing[] = $method;
            }
        }

        self::assertSame([], $missing);
    }
}
