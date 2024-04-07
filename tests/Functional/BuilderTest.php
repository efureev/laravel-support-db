<?php

declare(strict_types=1);

namespace Php\Support\Laravel\Database\Tests\Functional;

use Illuminate\Support\Facades\Schema;
use Php\Support\Laravel\Database\Tests\AbstractTestCase;
use Php\Support\Laravel\Database\Tests\Helpers\ExtensionsAssertions;
use PHPUnit\Framework\Attributes\Test;

class BuilderTest extends AbstractTestCase
{
    use ExtensionsAssertions;

    #[Test]
    public function createExtensionIfNotExists(): void
    {
        Schema::createExtensionIfNotExists('uuid-ossp');

        $this->assertHasExtension('uuid-ossp');
    }

    #[Test]
    public function dropExtensionIfExists(): void
    {
        $this->assertHasExtension('uuid-ossp');

        Schema::dropExtensionIfExists('uuid-ossp');
        $this->assertHasNotExtension('uuid-ossp');

        Schema::createExtensionIfNotExists('uuid-ossp');
        $this->assertHasExtension('uuid-ossp');
    }

    #[Test]
    public function createExtension(): void
    {
        $this->assertHasExtension('uuid-ossp');

        Schema::dropExtensionIfExists('uuid-ossp');
        $this->assertHasNotExtension('uuid-ossp');
    }

}
