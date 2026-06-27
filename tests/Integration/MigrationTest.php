<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration;

use M260605120000CreateSettingsTable;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[CoversNothing]
final class MigrationTest
{
    private ConnectionInterface $db;

    private MigrationBuilder $builder;

    #[BeforeTest]
    public function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/M260605120000CreateSettingsTable.php';

        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->builder = new MigrationBuilder(db: $this->db, informer: new NullMigrationInformer());
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function createsAndDropsSettingsTable(): void
    {
        $migration = new M260605120000CreateSettingsTable();

        $migration->up($this->builder);

        $schema = $this->db->getTableSchema('settings', true);
        Assert::notNull($schema);
        Assert::notNull($schema->getColumn('key'));
        Assert::notNull($schema->getColumn('value'));
        Assert::same($schema->getPrimaryKey(), ['key']);

        $migration->down($this->builder);

        Assert::null($this->db->getTableSchema('settings', true));
    }

    public function createsTableWithCustomName(): void
    {
        (new M260605120000CreateSettingsTable(table: 'custom_settings'))->up($this->builder);

        Assert::notNull($this->db->getTableSchema('custom_settings', true));
        Assert::null($this->db->getTableSchema('settings', true));
    }

    public function migratedTableIsReadableByProvider(): void
    {
        (new M260605120000CreateSettingsTable())->up($this->builder);

        $this->db->createCommand(
            sql: 'INSERT INTO settings ("key", "value") VALUES (:key, :value)',
        )->bindValues([
            ':key' => 'mail.from',
            ':value' => 'admin@example.com',
        ])->execute();

        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
            ],
        );

        Assert::same($provider->get('mail.from'), 'admin@example.com');
    }
}
