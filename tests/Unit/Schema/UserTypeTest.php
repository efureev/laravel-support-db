<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Builders\Indexes\PartialBuilder;
use Php\Support\Laravel\Database\Schema\Postgres\Compilers\UserTypeCompiler;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * Laravel can read a user-defined type back and drop every one of them at once, but has no way to
 * create a single one.
 */
final class UserTypeTest extends UnitTestCase
{
    #[Test]
    public function anEnumTypeListsItsValues(): void
    {
        self::assertSame(
            'create type "order_state" as enum (\'new\', \'paid\', \'shipped\')',
            UserTypeCompiler::enumType($this->grammar(), 'order_state', ['new', 'paid', 'shipped'])
        );
    }

    /** A value is a literal, so a quote in it is escaped rather than ending the string. */
    #[Test]
    public function aQuoteInAValueIsEscaped(): void
    {
        self::assertSame(
            'create type "name" as enum (\'O\'\'Brien\')',
            UserTypeCompiler::enumType($this->grammar(), 'name', ["O'Brien"])
        );
    }

    #[Test]
    public function anEnumNeedsAtLeastOneValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one value');

        UserTypeCompiler::enumType($this->grammar(), 'empty', []);
    }

    /** @return iterable<string, array{?string, ?string, string}> */
    public static function positions(): iterable
    {
        yield 'at the end' => [
            null, null, "alter type \"os\" add value 'refunded'",
        ];
        yield 'before another' => [
            'new', null, "alter type \"os\" add value 'refunded' before 'new'",
        ];
        yield 'after another' => [
            null, 'paid', "alter type \"os\" add value 'refunded' after 'paid'",
        ];
    }

    #[Test]
    #[DataProvider('positions')]
    public function anEnumValueMayBePositioned(?string $before, ?string $after, string $expected): void
    {
        self::assertSame(
            $expected,
            UserTypeCompiler::addEnumValue($this->grammar(), 'os', 'refunded', $before, $after)
        );
    }

    #[Test]
    public function aValueCannotBeBothBeforeAndAfter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('either before or after');

        UserTypeCompiler::addEnumValue($this->grammar(), 'os', 'x', 'a', 'b');
    }

    #[Test]
    public function aCompositeTypeNamesItsFields(): void
    {
        self::assertSame(
            'create type "full_name" as ("first" text, "last" varchar(30))',
            UserTypeCompiler::compositeType(
                $this->grammar(),
                'full_name',
                [
                    'first' => 'text',
                    'last'  => 'varchar(30)',
                ]
            )
        );
    }

    #[Test]
    public function aCompositeNeedsAtLeastOneField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one field');

        UserTypeCompiler::compositeType($this->grammar(), 'empty', []);
    }

    /**
     * The predicate names `value`. PostgreSQL spells the thing being checked `VALUE` and resolves
     * a quoted `"value"` to it, so the package's one predicate vocabulary reaches here unchanged.
     */
    #[Test]
    public function aDomainCarriesItsCheck(): void
    {
        $check = new PartialBuilder();
        $check->where('value', '>', 0);

        self::assertSame(
            'create domain "positive_int" as integer check (("value" > 0))',
            UserTypeCompiler::domain($this->grammar(), 'positive_int', 'integer', $check)
        );
    }

    #[Test]
    public function aDomainWithoutACheckIsJustAnAlias(): void
    {
        self::assertSame(
            'create domain "plain" as integer',
            UserTypeCompiler::domain($this->grammar(), 'plain', 'integer', null)
        );
    }

    #[Test]
    public function severalNamesAreDroppedInOneStatement(): void
    {
        self::assertSame(
            'drop type if exists "a", "b"',
            UserTypeCompiler::dropIfExists($this->grammar(), 'type', ['a', 'b'], false)
        );
        self::assertSame(
            'drop domain if exists "d" cascade',
            UserTypeCompiler::dropIfExists($this->grammar(), 'domain', ['d'], true)
        );
    }

    #[Test]
    public function droppingNothingIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name at least one type');

        UserTypeCompiler::dropIfExists($this->grammar(), 'type', [], false);
    }

    #[Test]
    public function aNameMayBeSchemaQualified(): void
    {
        self::assertSame(
            'drop type if exists "app"."tenant_state"',
            UserTypeCompiler::dropIfExists($this->grammar(), 'type', ['app.tenant_state'], false)
        );
    }

    /** @return iterable<string, array{string}> */
    public static function badTypes(): iterable
    {
        yield 'statement' => ['integer; drop table x'];
        yield 'quote' => ["integer'"];
        yield 'expression' => ['integer + 1'];
    }

    /** A base type is interpolated into DDL, so it is checked rather than trusted. */
    #[Test]
    #[DataProvider('badTypes')]
    public function anythingThatIsNotATypeNameIsRejected(string $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid type name');

        UserTypeCompiler::domain($this->grammar(), 'd', $type, null);
    }

    /** @return iterable<string, array{string}> */
    public static function goodTypes(): iterable
    {
        foreach (['integer', 'text', 'varchar(30)', 'numeric(10, 2)', 'text[]', 'double precision'] as $t) {
            yield $t => [$t];
        }
    }

    #[Test]
    #[DataProvider('goodTypes')]
    public function ordinaryTypeNamesAreAccepted(string $type): void
    {
        self::assertStringContainsString(
            "as $type",
            UserTypeCompiler::domain($this->grammar(), 'd', $type, null)
        );
    }
}
