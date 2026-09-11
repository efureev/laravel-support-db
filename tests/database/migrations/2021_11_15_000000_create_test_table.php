<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Schema\Postgres\Blueprint;

return new class extends Migration {
    private const TABLE = 'tests';

    public function up(): void
    {
        Schema::create(
            self::TABLE,
            static function (Blueprint $table): void {
                $table->primaryUUID();
                $table->string('name');
                $table->boolean('enabled');
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
};
