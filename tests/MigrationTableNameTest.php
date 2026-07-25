<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests;

use Rasuvaeff\Yii3SettingsDb\Migration\M260605120000CreateSettingsTable;
use Rasuvaeff\Yii3SettingsDb\SettingsTableName;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Migration\Informer\NullMigrationInformer;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Injector\Injector;
use Yiisoft\Test\Support\Container\SimpleContainer;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

/**
 * The migration is created by `yiisoft/db-migration` through `Injector::make()`,
 * not by the container, so a test that instantiates it directly proves nothing
 * about whether configuration actually reaches it. These go through the real
 * resolver.
 */
#[Test]
#[Covers(M260605120000CreateSettingsTable::class)]
final class MigrationTableNameTest
{
    private ConnectionInterface $db;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->db = new SqliteConnection(
            driver: new SqliteDriver(dsn: 'sqlite::memory:'),
            schemaCache: new SchemaCache(psrCache: new MemorySimpleCache()),
        );
    }

    public function containerBoundTableNameReachesTheMigration(): void
    {
        $migration = $this->make(new SimpleContainer([
            SettingsTableName::class => new SettingsTableName('custom_tbl'),
        ]));

        $migration->up($this->builder());

        Assert::notNull($this->db->getTableSchema('custom_tbl', true));
        Assert::null($this->db->getTableSchema('settings', true));
    }

    public function withoutABindingTheDefaultNameIsUsed(): void
    {
        // Injector falls back to the parameter default, so the package stays
        // usable with no configuration at all
        $migration = $this->make(new SimpleContainer([]));

        $migration->up($this->builder());

        Assert::notNull($this->db->getTableSchema('settings', true));
    }

    public function createsTheDocumentedColumnSet(): void
    {
        // the column list IS the contract with the runtime code: a column
        // silently dropped here surfaces only as a failing query in production
        $migration = $this->make(new SimpleContainer([]));

        $migration->up($this->builder());

        $schema = $this->db->getTableSchema('settings', true);
        Assert::notNull($schema);
        Assert::same(array_keys($schema->getColumns()), [
            'key',
            'value',
        ]);
    }

    public function downDropsTheConfiguredTable(): void
    {
        $migration = $this->make(new SimpleContainer([
            SettingsTableName::class => new SettingsTableName('custom_tbl'),
        ]));
        $builder = $this->builder();

        $migration->up($builder);
        $migration->down($builder);

        Assert::null($this->db->getTableSchema('custom_tbl', true));
    }

    private function make(SimpleContainer $container): M260605120000CreateSettingsTable
    {
        /** @var M260605120000CreateSettingsTable */
        return (new Injector($container))->make(M260605120000CreateSettingsTable::class);
    }

    private function builder(): MigrationBuilder
    {
        return new MigrationBuilder($this->db, new NullMigrationInformer());
    }
}
