<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\Unit\UnitTestCase;

/**
 * The framework accepts an operator class on a spatial or vector index and nowhere else: `index()`
 * takes no such argument, and `compileIndex()` would drop one if it somehow arrived. GIN is where
 * operator classes actually matter — `jsonb_path_ops` for containment queries, `gin_trgm_ops` for
 * trigram search — so `ginIndex()` takes one and the grammar honours it.
 */
final class IndexOperatorClassTest extends UnitTestCase
{
    #[Test]
    public function anOperatorClassFollowsTheColumn(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static function (Blueprint $table): void {
                $table->ginIndex('payload', 'docs_payload_gin', 'jsonb_path_ops');
            }
        );

        self::assertSame(
            'create index "docs_payload_gin" on "docs" using gin ("payload" jsonb_path_ops)',
            $sql[0]
        );
    }

    #[Test]
    public function everyColumnGetsTheOperatorClass(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static function (Blueprint $table): void {
                $table->ginIndex(['title', 'body'], 'docs_text_gin', 'gin_trgm_ops');
            }
        );

        self::assertSame(
            'create index "docs_text_gin" on "docs" using gin ("title" gin_trgm_ops, "body" gin_trgm_ops)',
            $sql[0]
        );
    }

    #[Test]
    public function withoutAnOperatorClassNothingChanges(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $table) => $table->ginIndex('tags', 'docs_tags_gin')
        );

        self::assertSame('create index "docs_tags_gin" on "docs" using gin ("tags")', $sql[0]);
    }

    /**
     * The override must not disturb the plain `create index` path it wraps.
     */
    #[Test]
    public function anOrdinaryIndexIsUntouched(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $table) => $table->index(['name'], 'docs_name_index')
        );

        self::assertSame('create index "docs_name_index" on "docs" ("name")', $sql[0]);
    }

    #[Test]
    public function theIndexNameIsGeneratedWhenOmitted(): void
    {
        $sql = $this->sqlFor(
            'docs',
            static fn(Blueprint $table) => $table->ginIndex('payload', null, 'jsonb_path_ops')
        );

        self::assertSame(
            'create index "docs_payload_index" on "docs" using gin ("payload" jsonb_path_ops)',
            $sql[0]
        );
    }

    /**
     * The table carries the prefix; an index name given explicitly is used verbatim, which is the
     * framework's rule — only a generated name picks the prefix up, through the prefixed table.
     */
    #[Test]
    public function theTablePrefixReachesTheTableAndTheGeneratedName(): void
    {
        $explicit = $this->sqlFor(
            'docs',
            static fn(Blueprint $table) => $table->ginIndex('payload', 'docs_payload_gin', 'jsonb_path_ops'),
            'pref_'
        );

        self::assertSame(
            'create index "docs_payload_gin" on "pref_docs" using gin ("payload" jsonb_path_ops)',
            $explicit[0]
        );

        $generated = $this->sqlFor(
            'docs',
            static fn(Blueprint $table) => $table->ginIndex('payload', null, 'jsonb_path_ops'),
            'pref_'
        );

        self::assertSame(
            'create index "pref_docs_payload_index" on "pref_docs" using gin ("payload" jsonb_path_ops)',
            $generated[0]
        );
    }
}
