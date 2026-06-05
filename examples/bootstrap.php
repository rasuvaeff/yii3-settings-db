<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Minimal in-memory PSR-16 cache so the example stays self-contained.
 */
function exampleSchemaCache(): SchemaCache
{
    $cache = new class implements CacheInterface {
        /** @var array<string, mixed> */
        private array $store = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->store[$key] ?? $default;
        }

        public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
        {
            $this->store[$key] = $value;

            return true;
        }

        public function delete(string $key): bool
        {
            unset($this->store[$key]);

            return true;
        }

        public function clear(): bool
        {
            $this->store = [];

            return true;
        }

        public function getMultiple(iterable $keys, mixed $default = null): iterable
        {
            return [];
        }

        public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
        {
            return true;
        }

        public function deleteMultiple(iterable $keys): bool
        {
            return true;
        }

        public function has(string $key): bool
        {
            return isset($this->store[$key]);
        }
    };

    return new SchemaCache(psrCache: $cache);
}

/**
 * @return array<string, SettingDefinition>
 */
function exampleDefinitions(): array
{
    return [
        'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
        'orders.max_items' => new SettingDefinition(key: 'orders.max_items', type: SettingType::Int, default: 100),
    ];
}

function exampleConnection(): ConnectionInterface
{
    $driver = new SqliteDriver(dsn: 'sqlite::memory:');
    $db = new SqliteConnection(driver: $driver, schemaCache: exampleSchemaCache());
    $db->open();
    $db->createCommand(sql: 'CREATE TABLE settings ("key" VARCHAR(190) PRIMARY KEY, "value" TEXT NOT NULL)')->execute();

    return $db;
}
