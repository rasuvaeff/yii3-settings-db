<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Tests\NullPsr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;

require dirname(__DIR__) . '/vendor/autoload.php';

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
    $schemaCache = new SchemaCache(psrCache: new NullPsr16Cache());
    $db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
    $db->open();
    $db->createCommand(sql: 'CREATE TABLE settings ("key" VARCHAR(190) PRIMARY KEY, "value" TEXT NOT NULL)')->execute();

    return $db;
}
