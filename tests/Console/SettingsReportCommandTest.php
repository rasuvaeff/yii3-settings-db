<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Console;

use Rasuvaeff\Yii3Settings\Crypto\Cipher;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Console\SettingsReportCommand;
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
#[Covers(SettingsReportCommand::class)]
final class SettingsReportCommandTest
{
    private ConnectionInterface $db;

    #[BeforeTest]
    public function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->db->createCommand(sql: 'DROP TABLE IF EXISTS settings')->execute();
        $this->db->createCommand(sql: 'CREATE TABLE settings ("key" VARCHAR(190) PRIMARY KEY, "value" TEXT NOT NULL)')->execute();
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function reportsNoProblemsWhenTableIsEmpty(): void
    {
        $tester = $this->runCommand(
            definitions: [
                'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
            ],
        );

        $expected = sprintf(
            "Setting sources:\n  %-40s %s\nNo problems found.\n",
            'mail.from',
            'default',
        );

        Assert::same($tester->getStatusCode(), 0);
        Assert::same($tester->getDisplay(), $expected);
    }

    public function printsSourceForDbOverrideAndUnsetKeys(): void
    {
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
                'debug.enabled' => new SettingDefinition(key: 'debug.enabled', type: SettingType::Bool, default: false),
            ],
        );
        $provider->set('mail.from', 'admin@example.com');

        $tester = new CommandTester(new SettingsReportCommand($provider));
        $tester->execute([]);

        $expected = sprintf(
            "Setting sources:\n  %-40s %s\n  %-40s %s\nNo problems found.\n",
            'mail.from',
            'db',
            'debug.enabled',
            'default',
        );

        Assert::same($tester->getStatusCode(), 0);
        Assert::same($tester->getDisplay(), $expected);
    }

    public function reportsStaleKeysAndFails(): void
    {
        $this->insertRawRow(key: 'legacy.flag', value: '1');

        $tester = $this->runCommand(
            definitions: [
                'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
            ],
        );

        $expected = sprintf(
            "Setting sources:\n  %-40s %s\nStale stored keys (no definition; prune with settings:prune --apply):\n  %s\n%d problem(s) found.\n",
            'mail.from',
            'default',
            'legacy.flag',
            1,
        );

        Assert::same($tester->getStatusCode(), 1);
        Assert::same($tester->getDisplay(), $expected);
    }

    public function reportsInvalidStoredValueAndFails(): void
    {
        $this->insertRawRow(key: 'max.items', value: 'abc');

        $tester = $this->runCommand(
            definitions: [
                'max.items' => new SettingDefinition(key: 'max.items', type: SettingType::Int, default: 10),
            ],
        );

        $expected = sprintf(
            "Setting sources:\n  %-40s %s\nInvalid %s value for \"%s\": %s\n%d problem(s) found.\n",
            'max.items',
            'db',
            'db',
            'max.items',
            'Invalid stored int for setting "max.items": got string',
            1,
        );

        Assert::same($tester->getStatusCode(), 1);
        Assert::same($tester->getDisplay(), $expected);
    }

    public function validatesStoredSecretWithoutPrintingItsValue(): void
    {
        $this->insertRawRow(key: 'api.key', value: 'corrupt-envelope');

        $cipher = new class implements Cipher {
            #[\Override]
            public function encrypt(string $plaintext, string $aad = ''): string
            {
                return $plaintext . '.enc';
            }

            #[\Override]
            public function decrypt(string $ciphertext, string $aad = ''): string
            {
                throw new \RuntimeException('Failed to decrypt value');
            }
        };

        $tester = $this->runCommand(
            definitions: [
                'api.key' => new SettingDefinition(key: 'api.key', type: SettingType::String, secret: true),
            ],
            cipher: $cipher,
        );

        $display = $tester->getDisplay();
        $expected = sprintf(
            "Setting sources:\n  %-40s %s\nInvalid %s value for \"%s\": %s\n%d problem(s) found.\n",
            'api.key',
            'db',
            'db',
            'api.key',
            'Failed to decrypt value',
            1,
        );

        Assert::same($tester->getStatusCode(), 1);
        Assert::same($display, $expected);
        Assert::false(str_contains($display, 'corrupt-envelope'));
    }

    public function passesWhenStoredSecretDecrypts(): void
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
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'api.key' => new SettingDefinition(key: 'api.key', type: SettingType::String, secret: true),
            ],
            cipher: $cipher,
        );
        $provider->set('api.key', 'hunter2');

        $tester = new CommandTester(new SettingsReportCommand($provider));
        $tester->execute([]);

        $expected = sprintf(
            "Setting sources:\n  %-40s %s\nNo problems found.\n",
            'api.key',
            'db',
        );

        $display = $tester->getDisplay();
        Assert::same($tester->getStatusCode(), 0);
        Assert::same($display, $expected);
        Assert::false(str_contains($display, 'hunter2'));
    }

    public function continuesPastInvalidKeyAndCountsAllProblems(): void
    {
        $this->insertRawRow(key: 'max.items', value: 'abc');
        $this->insertRawRow(key: 'legacy.flag', value: '1');

        $tester = $this->runCommand(
            definitions: [
                'max.items' => new SettingDefinition(key: 'max.items', type: SettingType::Int, default: 10),
                'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
            ],
        );

        $expected = sprintf(
            "Setting sources:\n  %-40s %s\nInvalid %s value for \"%s\": %s\n  %-40s %s\nStale stored keys (no definition; prune with settings:prune --apply):\n  %s\n%d problem(s) found.\n",
            'max.items',
            'db',
            'db',
            'max.items',
            'Invalid stored int for setting "max.items": got string',
            'mail.from',
            'default',
            'legacy.flag',
            2,
        );

        Assert::same($tester->getStatusCode(), 1);
        Assert::same($tester->getDisplay(), $expected);
    }

    public function labelsAnInvalidConfigValueByItsRealSource(): void
    {
        $fallback = new class implements SettingsProvider {
            #[\Override]
            public function has(string $key): bool
            {
                return true;
            }

            #[\Override]
            public function get(string $key): mixed
            {
                throw new \UnexpectedValueException('Config value is not an int');
            }
        };

        $tester = $this->runCommand(
            definitions: [
                'max.items' => new SettingDefinition(key: 'max.items', type: SettingType::Int, default: 10),
            ],
            fallback: $fallback,
        );

        $expected = sprintf(
            "Setting sources:\n  %-40s %s\nInvalid %s value for \"%s\": %s\n%d problem(s) found.\n",
            'max.items',
            'config',
            'config',
            'max.items',
            'Config value is not an int',
            1,
        );

        Assert::same($tester->getStatusCode(), 1);
        Assert::same($tester->getDisplay(), $expected);
    }

    /**
     * @param array<string, SettingDefinition> $definitions
     */
    private function runCommand(array $definitions, ?Cipher $cipher = null, ?SettingsProvider $fallback = null): CommandTester
    {
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: $definitions,
            fallback: $fallback,
            cipher: $cipher,
        );

        $tester = new CommandTester(new SettingsReportCommand($provider));
        $tester->execute([]);

        return $tester;
    }

    private function insertRawRow(string $key, string $value): void
    {
        $this->db->createCommand()
            ->upsert('settings', ['key' => $key, 'value' => $value])
            ->execute();
    }
}
