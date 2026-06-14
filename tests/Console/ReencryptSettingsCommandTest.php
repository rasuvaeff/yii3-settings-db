<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3Settings\Crypto\Cipher;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Console\ReencryptSettingsCommand;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Symfony\Component\Console\Tester\CommandTester;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[CoversClass(ReencryptSettingsCommand::class)]
final class ReencryptSettingsCommandTest extends TestCase
{
    private ConnectionInterface $db;

    /** @var array<string, SettingDefinition> */
    private array $definitions;

    #[\Override]
    protected function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->db->createCommand(sql: 'DROP TABLE IF EXISTS settings')->execute();
        $this->db->createCommand(sql: 'CREATE TABLE settings ("key" VARCHAR(190) PRIMARY KEY, "value" TEXT NOT NULL)')->execute();

        $this->definitions = [
            'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
        ];
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->db->close();
    }

    #[Test]
    public function reportsZeroCountWhenNoSecretsStored(): void
    {
        $command = new ReencryptSettingsCommand($this->provider());
        $tester = new CommandTester($command);

        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Re-encrypted 0 secret setting(s).', $tester->getDisplay());
    }

    private function provider(): DbSettingsProvider
    {
        $cipher = new class implements Cipher {
            #[\Override]
            public function encrypt(string $plaintext, string $aad = ''): string
            {
                return $plaintext . '.enc';
            }

            #[\Override]
            public function decrypt(string $ciphertext, string $aad = ''): string
            {
                return substr($ciphertext, 0, -4);
            }
        };

        return new DbSettingsProvider(
            db: $this->db,
            definitions: $this->definitions,
            cipher: $cipher,
        );
    }
}
