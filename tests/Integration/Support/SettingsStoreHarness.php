<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration\Support;

use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

/**
 * Stateful-test harness around a {@see DbSettingsProvider} backed by a fresh
 * in-memory SQLite database. Keys are addressed by index; each is an Int setting
 * with default 0.
 *
 * The system under test for the model-based property in
 * {@see \Rasuvaeff\Yii3SettingsDb\Tests\Integration\DbSettingsProviderStatefulTest}.
 */
final class SettingsStoreHarness
{
    public const int DEFAULT_VALUE = 0;

    private readonly ConnectionInterface $db;
    private readonly DbSettingsProvider $provider;

    /** @var list<string> */
    private readonly array $keys;

    public function __construct(int $settingCount)
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $this->db = new SqliteConnection(driver: $driver, schemaCache: new SchemaCache(psrCache: new MemorySimpleCache()));
        $this->db->open();
        $this->db->createCommand(sql: 'CREATE TABLE settings ("key" VARCHAR(190) PRIMARY KEY, "value" TEXT NOT NULL)')->execute();

        $this->keys = array_map(
            static fn(int $i): string => 'setting-' . $i,
            range(0, $settingCount - 1),
        );

        $definitions = [];
        foreach ($this->keys as $key) {
            $definitions[$key] = new SettingDefinition(key: $key, type: SettingType::Int, default: self::DEFAULT_VALUE);
        }

        $this->provider = new DbSettingsProvider(db: $this->db, definitions: $definitions);
    }

    public function set(int $index, int $value): void
    {
        $this->provider->set($this->keys[$index], $value);
    }

    public function remove(int $index): void
    {
        $this->provider->remove($this->keys[$index]);
    }

    /**
     * The effective value of each of the first $count keys (stored value, or the
     * definition default when no row exists) — mirrors the model.
     *
     * @return list<mixed>
     */
    public function snapshot(int $count): array
    {
        return array_map(
            fn(int $i): mixed => $this->provider->get($this->keys[$i]),
            range(0, $count - 1),
        );
    }
}
