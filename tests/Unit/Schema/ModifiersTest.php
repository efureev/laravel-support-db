<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Schema\Postgres\Grammar;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Regression tests for D1: `addModifier()` used the array union operator on a list, which
 * overwrote the element at key 0 (`Collate`) instead of shifting it.
 */
final class ModifiersTest extends UnitTestCase
{
    #[Test]
    public function addModifierKeepsEveryInheritedModifier(): void
    {
        $grammar = new Grammar($this->connection());

        $before = $this->modifiersOf($grammar);

        $grammar->addModifier('Compression');

        $after = $this->modifiersOf($grammar);

        self::assertSame('Compression', $after[0], 'the new modifier must come first');
        self::assertSame([], array_diff($before, $after), 'no inherited modifier may be dropped');
        self::assertSame(count($before) + 1, count($after));
    }

    #[Test]
    public function addModifierIsIdempotent(): void
    {
        $grammar = new Grammar($this->connection());

        $grammar->addModifier('Compression')->addModifier('Compression');

        $modifiers = $this->modifiersOf($grammar);

        self::assertSame(['Compression'], array_values(array_filter(
            $modifiers,
            static fn(string $modifier): bool => $modifier === 'Compression'
        )));
    }

    #[Test]
    public function collationSurvivesOnTheCreatePath(): void
    {
        $sql = $this->sqlForCreate('t', static function (Blueprint $table): void {
            $table->string('name')->collation('C');
        });

        self::assertStringContainsString('collate "C"', $sql[0]);
    }

    /**
     * @return list<string>
     */
    private function modifiersOf(Grammar $grammar): array
    {
        $property = new \ReflectionProperty($grammar, 'modifiers');

        return array_values($property->getValue($grammar));
    }
}
