<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional\Schemas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;

/**
 * `relpersistence` and `reloptions` are what the server actually recorded, which is the only
 * evidence that matters here.
 */
class StorageTest extends AbstractTestCase
{
    protected function tearDown(): void
    {
        Schema::dropIfExists('test_table');

        parent::tearDown();
    }

    #[Test]
    public function anUnloggedTableIsRecordedAsUnlogged(): void
    {
        Schema::create('test_table', static function (Blueprint $t) {
            $t->string('k');
            $t->unlogged();
        });

        self::assertSame('u', $this->relation()->relpersistence);
    }

    #[Test]
    public function anOrdinaryTableIsPermanent(): void
    {
        Schema::create('test_table', static fn(Blueprint $t) => $t->string('k'));

        self::assertSame('p', $this->relation()->relpersistence);
    }

    #[Test]
    public function storageParametersReachTheCatalogue(): void
    {
        Schema::create('test_table', static function (Blueprint $t) {
            $t->string('k');
            $t->storageParameters(['fillfactor' => 70, 'autovacuum_vacuum_scale_factor' => 0.05]);
        });

        $options = $this->relation()->reloptions;

        self::assertStringContainsString('fillfactor=70', $options);
        self::assertStringContainsString('autovacuum_vacuum_scale_factor=0.05', $options);
    }

    #[Test]
    public function theyCanBeSetOnAnExistingTable(): void
    {
        Schema::create('test_table', static fn(Blueprint $t) => $t->string('k'));

        Schema::table('test_table', static fn(Blueprint $t) => $t->storageParameters(['fillfactor' => 60]));

        self::assertStringContainsString('fillfactor=60', $this->relation()->reloptions);
    }

    #[Test]
    public function resettingPutsThemBack(): void
    {
        Schema::create('test_table', static function (Blueprint $t) {
            $t->string('k');
            $t->storageParameters(['fillfactor' => 70]);
        });

        Schema::table('test_table', static fn(Blueprint $t) => $t->resetStorageParameters('fillfactor'));

        self::assertStringNotContainsString('fillfactor', (string)$this->relation()->reloptions);
    }

    private function relation(): object
    {
        return DB::selectOne(
            'select relpersistence, reloptions from pg_class where relname = ?',
            ['test_table']
        );
    }
}
