<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration;

use M260605120000CreateSettingsTable;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[CoversNothing]
final class MigrationTest extends TestCase
{
    private ConnectionInterface $db;

    private MigrationBuilder $builder;

    #[\Override]
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/migrations/M260605120000CreateSettingsTable.php';

        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->builder = new MigrationBuilder(db: $this->db, informer: new NullMigrationInformer());
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->db->close();
    }

    #[Test]
    public function createsAndDropsSettingsTable(): void
    {
        $migration = new M260605120000CreateSettingsTable();

        $migration->up($this->builder);

        $schema = $this->db->getTableSchema('settings', true);
        $this->assertNotNull($schema);
        $this->assertNotNull($schema->getColumn('key'));
        $this->assertNotNull($schema->getColumn('value'));
        $this->assertSame(['key'], $schema->getPrimaryKey());

        $migration->down($this->builder);

        $this->assertNull($this->db->getTableSchema('settings', true));
    }

    #[Test]
    public function createsTableWithCustomName(): void
    {
        (new M260605120000CreateSettingsTable(table: 'custom_settings'))->up($this->builder);

        $this->assertNotNull($this->db->getTableSchema('custom_settings', true));
        $this->assertNull($this->db->getTableSchema('settings', true));
    }

    #[Test]
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

        $this->assertSame('admin@example.com', $provider->get('mail.from'));
    }
}
