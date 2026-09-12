<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit;

use Illuminate\Foundation\Application;
use Illuminate\Database\Connection as BaseConnection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Php\Support\Laravel\Database\ServiceProvider;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

/**
 * `Connection::$resolvers` is a static registry that the framework writes to but never clears —
 * `resolverFor()` is its only writer in the whole of `vendor/`. Anything the registered closure
 * captures therefore lives for the rest of the process.
 *
 * Capturing `$this->app` would be the easy way to write this resolver and would leak a dead
 * container out of every Testbench case that registered one. The four arguments the framework
 * passes are enough, so the closure must hold nothing at all.
 *
 * That the resolver produces the package's Connection is asserted in the functional
 * `ConnectionTest`; what is asserted here is that it does so without holding a reference.
 */
final class ConnectionResolverTest extends TestCase
{
    #[Test]
    public function theResolverClosureCapturesNothing(): void
    {
        $closure = new ReflectionFunction($this->registerAndFetchResolver());

        // A bound $this pins the service provider, and through it the container, for the
        // lifetime of the process.
        self::assertNull($closure->getClosureThis(), 'The resolver must be a static closure.');

        // The registry is never cleared, so anything captured here outlives the request that
        // registered it.
        self::assertSame([], $closure->getClosureUsedVariables(), 'The resolver must import nothing.');
    }

    /**
     * The arguments the framework hands the resolver are the only input it may use — four of them,
     * in this order. A closure that needed a fifth would have to capture something.
     */
    #[Test]
    public function theResolverTakesEverythingItNeedsAsArguments(): void
    {
        $closure = new ReflectionFunction($this->registerAndFetchResolver());

        self::assertSame(
            [
                'connection',
                'database',
                'prefix',
                'config',
            ],
            array_map(
                static fn(ReflectionParameter $parameter): string => $parameter->getName(),
                $closure->getParameters()
            )
        );
    }

    /**
     * Registering twice must not stack up state; the container handed to the provider is not the
     * one the resolver uses, and a second provider replaces the first cleanly.
     */
    #[Test]
    public function reRegisteringDoesNotRetainTheEarlierContainer(): void
    {
        $first  = new ReflectionFunction($this->registerAndFetchResolver());
        $second = new ReflectionFunction($this->registerAndFetchResolver());

        self::assertNull($first->getClosureThis());
        self::assertNull($second->getClosureThis());
        self::assertSame([], $second->getClosureUsedVariables());
    }

    private function registerAndFetchResolver(): callable
    {
        (new ReflectionMethod(ServiceProvider::class, 'registerConnectionResolver'))
            ->invoke(new ServiceProvider(new Application()));

        $resolver = BaseConnection::getResolver('pgsql');

        self::assertNotNull($resolver, 'the provider must register a pgsql resolver');

        return $resolver;
    }
}
