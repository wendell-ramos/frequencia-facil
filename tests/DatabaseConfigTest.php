<?php

declare(strict_types=1);

use App\Config\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    private string $secretFile;

    protected function setUp(): void
    {
        $this->secretFile = tempnam(sys_get_temp_dir(), 'ff-db-secret-');
        putenv('MYSQL_PASSWORD');
        putenv('MYSQL_PASSWORD_FILE');
    }

    protected function tearDown(): void
    {
        if (is_file($this->secretFile)) {
            unlink($this->secretFile);
        }
        putenv('MYSQL_PASSWORD');
        putenv('MYSQL_PASSWORD_FILE');
    }

    public function testReadsPasswordFromDockerSecretAndRemovesOnlyLineEnding(): void
    {
        file_put_contents($this->secretFile, " senha-com-espacos \n");
        putenv('MYSQL_PASSWORD_FILE=' . $this->secretFile);

        self::assertSame(' senha-com-espacos ', $this->readPassword());
    }

    public function testSecretTakesPriorityOverEnvironmentVariable(): void
    {
        file_put_contents($this->secretFile, "senha-do-secret\n");
        putenv('MYSQL_PASSWORD=senha-do-env');
        putenv('MYSQL_PASSWORD_FILE=' . $this->secretFile);

        self::assertSame('senha-do-secret', $this->readPassword());
    }

    public function testFallsBackToEnvironmentVariableForLocalDevelopment(): void
    {
        putenv('MYSQL_PASSWORD=senha-local');

        self::assertSame('senha-local', $this->readPassword());
    }

    public function testRejectsMissingSecretInsteadOfFallingBackSilently(): void
    {
        putenv('MYSQL_PASSWORD=nao-deve-ser-usada');
        putenv('MYSQL_PASSWORD_FILE=' . $this->secretFile . '.ausente');

        $this->expectException(RuntimeException::class);
        $this->readPassword();
    }

    private function readPassword(): string
    {
        $method = new ReflectionMethod(Database::class, 'readPassword');
        return $method->invoke(null);
    }
}
