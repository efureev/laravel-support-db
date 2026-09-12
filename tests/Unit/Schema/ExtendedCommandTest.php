<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use Illuminate\Database\Schema\Blueprint as FrameworkBlueprint;
use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Definitions\LikeDefinition;
use Php\Support\Laravel\Database\Schema\Definitions\ViewDefinition;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\Unique\UniqueBuilder;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;
use ReflectionMethod;

/**
 * `addExtendedCommand()` exists only because `addCommand()` hard-codes `Fluent` and offers no hook
 * for a command class of our own. It is therefore a deliberate copy of framework code, and a copy
 * is exactly the thing that drifts: if a future Laravel teaches `createCommand()` to set another
 * field, ours would quietly stop setting it.
 *
 * These assert the copy still agrees with the original, field for field.
 */
final class ExtendedCommandTest extends UnitTestCase
{
    /** @return iterable<string, array{class-string<Fluent<string, mixed>>, string, array<string, mixed>}> */
    public static function extendedCommands(): iterable
    {
        yield 'createView' => [
            ViewDefinition::class,
            'createView',
            [
                'view'        => 'v',
                'select'      => 'select 1',
                'materialize' => false,
            ],
        ];
        yield 'createViewOrReplace' => [
            ViewDefinition::class,
            'createViewOrReplace',
            [
                'view'        => 'v',
                'select'      => 'select 1',
                'materialize' => true,
            ],
        ];
        yield 'like' => [
            LikeDefinition::class,
            'like',
            ['table' => 'src'],
        ];
        yield 'partial' => [
            PartialBuilder::class,
            'partial',
            [
                'columns'   => ['a'],
                'index'     => 't_a_partial',
                'algorithm' => null,
            ],
        ];
        yield 'uniquePartial' => [
            UniqueBuilder::class,
            'uniquePartial',
            [
                'columns'   => ['a'],
                'index'     => 't_a_unique',
                'algorithm' => 'btree',
            ],
        ];
        // The framework merges the parameters *over* the name, so a parameter called `name` wins.
        // Surprising, but ours must be surprising in the same way.
        yield 'a name parameter overrides the command name' => [
            ViewDefinition::class,
            'createView',
            ['name' => 'overridden'],
        ];
        yield 'no parameters at all' => [
            LikeDefinition::class,
            'like',
            [],
        ];
    }

    /**
     * @param class-string<Fluent<string, mixed>> $class
     * @param array<string, mixed>                $parameters
     */
    #[Test]
    #[DataProvider('extendedCommands')]
    public function anExtendedCommandCarriesTheSameFieldsAsTheFrameworks(
        string $class,
        string $name,
        array $parameters
    ): void {
        $blueprint = $this->blueprint('t');

        $createCommand = new ReflectionMethod($blueprint, 'createCommand');

        // Without this the test would compare the package against itself and pass no matter what.
        self::assertSame(
            FrameworkBlueprint::class,
            $createCommand->getDeclaringClass()->getName(),
            'createCommand() must stay the framework\'s: the comparison is meaningless otherwise.'
        );

        $theirs = $createCommand->invoke($blueprint, $name, $parameters);
        $ours   = (new ReflectionMethod($blueprint, 'addExtendedCommand'))
            ->invoke($blueprint, $class, $name, $parameters);

        self::assertSame($theirs->getAttributes(), $ours->getAttributes());
    }

    /**
     * The whole point of the copy: the command is an instance of the requested class, not `Fluent`.
     *
     * @param class-string<Fluent<string, mixed>> $class
     * @param array<string, mixed>                $parameters
     */
    #[Test]
    #[DataProvider('extendedCommands')]
    public function anExtendedCommandIsAFluentOfTheRequestedClass(
        string $class,
        string $name,
        array $parameters
    ): void {
        $blueprint = $this->blueprint('t');

        $command = (new ReflectionMethod($blueprint, 'addExtendedCommand'))
            ->invoke($blueprint, $class, $name, $parameters);

        self::assertInstanceOf($class, $command);
        self::assertInstanceOf(Fluent::class, $command);
    }

    /**
     * `addCommand()` appends to `$commands` and returns the same instance; so must ours, or the
     * statement would be built without ever being compiled.
     */
    #[Test]
    public function anExtendedCommandIsAppendedToTheBlueprintAndReturned(): void
    {
        $blueprint = $this->blueprint('t');
        $first     = $blueprint->createView('v', 'select 1');
        $second    = $blueprint->like('src');

        self::assertSame([$first, $second], array_values($blueprint->getCommands()));
    }
}
