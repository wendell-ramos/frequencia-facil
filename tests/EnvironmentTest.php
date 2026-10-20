<?php

declare(strict_types=1);

use App\Config\Environment;
use PHPUnit\Framework\TestCase;

final class EnvironmentTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'frequencia-env-');
    }

    protected function tearDown(): void
    {
        unlink($this->file);
        foreach (['FF_TEST_VALUE', 'FF_TEST_PASSWORD'] as $name) {
            putenv($name);
            unset($_ENV[$name]);
        }
    }

    public function testEnvironmentTakesPriorityOverLocalFile(): void
    {
        putenv('FF_TEST_VALUE=docker');
        file_put_contents($this->file, "FF_TEST_VALUE=local\n");
        Environment::load($this->file);
        self::assertSame('docker', getenv('FF_TEST_VALUE'));
    }

    public function testEmptyEnvironmentValueIsPreserved(): void
    {
        putenv('FF_TEST_PASSWORD=');
        file_put_contents($this->file, "FF_TEST_PASSWORD=local\n");
        Environment::load($this->file);
        self::assertSame('', getenv('FF_TEST_PASSWORD'));
    }

    public function testPasswordKeepsHashAndEqualsInsideQuotes(): void
    {
        file_put_contents($this->file, "# exemplo\nFF_TEST_PASSWORD=\"senha#com=simbolos\"\n");
        Environment::load($this->file);
        self::assertSame('senha#com=simbolos', getenv('FF_TEST_PASSWORD'));
    }

    public function testMissingFileIsAllowedForDocker(): void
    {
        putenv('FF_TEST_VALUE=docker');
        Environment::load($this->file . '.ausente');
        self::assertSame('docker', getenv('FF_TEST_VALUE'));
    }

    public function testMalformedConfigurationIsRejected(): void
    {
        file_put_contents($this->file, "linha sem sinal de igual\n");
        $this->expectException(RuntimeException::class);
        Environment::load($this->file);
    }
}
