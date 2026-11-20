<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests;

use Rasuvaeff\Yii3Settings\Exception\UnknownSettingException;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Testo\Assert;
use Testo\Assert\ExpectException;
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
#[Covers(DbSettingsProvider::class)]
final class DbSettingsProviderMaintenanceTest
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

    public function sourceOfReportsDbConfigAndDefault(): void
    {
        $fallback = new class implements SettingsProvider {
            #[\Override]
            public function has(string $key): bool
            {
                return $key === 'from.config';
            }

            #[\Override]
            public function get(string $key): mixed
            {
                return 'x';
            }
        };
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'from.db' => new SettingDefinition(key: 'from.db', type: SettingType::String, default: 'd'),
                'from.config' => new SettingDefinition(key: 'from.config', type: SettingType::String, default: 'd'),
                'from.default' => new SettingDefinition(key: 'from.default', type: SettingType::String, default: 'd'),
            ],
            fallback: $fallback,
        );
        $this->db->createCommand()->upsert('settings', ['key' => 'from.db', 'value' => 'v'])->execute();

        Assert::same($provider->sourceOf('from.db'), 'db');
        Assert::same($provider->sourceOf('from.config'), 'config');
        Assert::same($provider->sourceOf('from.default'), 'default');
    }

    #[ExpectException(UnknownSettingException::class)]
    public function sourceOfRejectsUnknownKeys(): void
    {
        $this->provider()->sourceOf('nope');
    }

    public function staleKeysEmptyWhenTableIsEmpty(): void
    {
        Assert::same($this->provider()->staleKeys(), []);
    }

    public function staleKeysIgnoreDefinedRows(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');

        Assert::same($provider->staleKeys(), []);
    }

    public function staleKeysListUnknownStoredRows(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');
        $this->insertRawRow(key: 'legacy.flag', value: '1');
        $this->insertRawRow(key: 'old.value', value: 'gone');

        Assert::same($provider->staleKeys(), ['legacy.flag', 'old.value']);
    }

    public function pruneStaleReturnsZeroWhenNothingStale(): void
    {
        Assert::same($this->provider()->pruneStale(), 0);
    }

    public function pruneStaleRemovesStaleRowsAndKeepsDefinedOnes(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');
        $this->insertRawRow(key: 'legacy.flag', value: '1');
        $this->insertRawRow(key: 'old.value', value: 'gone');

        Assert::same($provider->pruneStale(), 2);
        Assert::same($provider->staleKeys(), []);
        Assert::same($provider->get('mail.from'), 'admin@example.com');
    }

    private function provider(): DbSettingsProvider
    {
        return new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
            ],
        );
    }

    private function insertRawRow(string $key, string $value): void
    {
        $this->db->createCommand()
            ->upsert('settings', ['key' => $key, 'value' => $value])
            ->execute();
    }
}
