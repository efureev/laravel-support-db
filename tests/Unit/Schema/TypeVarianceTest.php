<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use Illuminate\Database\Schema\Blueprint as BaseBlueprint;
use Illuminate\Support\Fluent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Laravel dispatches grammar methods by name, handing them the framework's own `Blueprint` and
 * `Fluent`. Narrowing those parameters to the package's own subclasses works only as long as
 * every blueprint happens to be built by this package — a custom `blueprintResolver`, a
 * `BlueprintState` or a third-party macro turns it into a fatal `TypeError`.
 */
final class TypeVarianceTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('dispatchedMethods')]
    public function dispatchedMethodsAcceptTheFrameworkTypes(string $method): void
    {
        foreach ((new ReflectionMethod(Grammar::class, $method))->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();

            if (is_a($name, BaseBlueprint::class, true)) {
                self::assertSame(
                    BaseBlueprint::class,
                    $name,
                    "$method(): blueprint parameter must not be narrower than the framework's"
                );
            }

            if (is_a($name, Fluent::class, true) && !str_contains($name, 'Builders\\')) {
                self::assertSame(
                    Fluent::class,
                    $name,
                    "$method(): Fluent parameter must not be narrower than the framework's"
                );
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function dispatchedMethods(): iterable
    {
        foreach ((new ReflectionClass(Grammar::class))->getMethods() as $method) {
            // Everything Laravel reaches by building a method name from a column or command...
            if (preg_match('/^(type|modify|compile)[A-Z]/', $method->getName()) !== 1) {
                continue;
            }

            // ...that this package declares, and that takes something to narrow in the first place.
            if (!str_starts_with($method->getDeclaringClass()->getName(), 'Php\\Support\\')) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    yield $method->getName() => [$method->getName()];
                    continue 2;
                }
            }
        }
    }

    /**
     * A blueprint that is not the package's own must still compile.
     */
    #[Test]
    public function aFrameworkBlueprintCompilesThroughThePackageGrammar(): void
    {
        $blueprint = new BaseBlueprint($this->connection(), 't');
        $blueprint->create();
        $blueprint->string('name')->collation('C');

        self::assertSame(
            'create table "t" ("name" varchar(255) collate "C" not null)',
            $blueprint->toSql()[0]
        );
    }
}
