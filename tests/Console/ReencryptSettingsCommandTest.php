<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Console;

use Rasuvaeff\Yii3Settings\Crypto\Cipher;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Console\ReencryptSettingsCommand;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[Covers(ReencryptSettingsCommand::class)]
final class ReencryptSettingsCommandTest
{
    private ConnectionInterface $db;

    /** @var array<string, SettingDefinition> */
    private array $definitions;

    #[BeforeTest]
    public function setUp(): void
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

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function reportsZeroCountWhenNoSecretsStored(): void
    {
        $command = new ReencryptSettingsCommand($this->provider());
        $tester = new CommandTester($command);

        $tester->execute([]);

        Assert::same($tester->getStatusCode(), 0);
        Assert::string($tester->getDisplay())->contains('Re-encrypted 0 secret setting(s).');
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
