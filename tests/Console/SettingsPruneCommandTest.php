<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Console;

use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Console\SettingsPruneCommand;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Symfony\Component\Console\Tester\CommandTester;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[Covers(SettingsPruneCommand::class)]
final class SettingsPruneCommandTest
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

    public function reportsEmptyWhenNothingStale(): void
    {
        $tester = $this->runCommand(apply: false);

        Assert::same($tester->getStatusCode(), 0);
        Assert::same($tester->getDisplay(), "No stale stored keys.\n");
    }

    public function dryRunListsStaleKeysWithoutRemovingThem(): void
    {
        $provider = $this->providerWithStaleRows();

        $tester = new CommandTester(new SettingsPruneCommand($provider));
        $tester->execute([]);

        $expected = "Stale stored keys (no definition):\n  legacy.flag\n  old.value\n2 key(s). Run with --apply to remove.\n";

        Assert::same($tester->getStatusCode(), 0);
        Assert::same($tester->getDisplay(), $expected);
        Assert::same($this->storedRowCount(), 3);
    }

    public function applyRemovesStaleKeysOnly(): void
    {
        $provider = $this->providerWithStaleRows();

        $tester = new CommandTester(new SettingsPruneCommand($provider));
        $tester->execute(['--apply' => true]);

        Assert::same($tester->getStatusCode(), 0);
        Assert::same($tester->getDisplay(), "Removed 2 stale key(s).\n");
        Assert::same($provider->staleKeys(), []);
        Assert::same($provider->get('mail.from'), 'admin@example.com');
        Assert::same($this->storedRowCount(), 1);
    }

    /**
     * @return DbSettingsProvider provider with one defined override row and two stale rows
     */
    private function providerWithStaleRows(): DbSettingsProvider
    {
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
            ],
        );
        $provider->set('mail.from', 'admin@example.com');
        $this->insertRawRow(key: 'legacy.flag', value: '1');
        $this->insertRawRow(key: 'old.value', value: 'gone');

        return $provider;
    }

    private function runCommand(bool $apply): CommandTester
    {
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: [],
        );

        $tester = new CommandTester(new SettingsPruneCommand($provider));
        $tester->execute($apply ? ['--apply' => true] : []);

        return $tester;
    }

    private function insertRawRow(string $key, string $value): void
    {
        $this->db->createCommand()
            ->upsert('settings', ['key' => $key, 'value' => $value])
            ->execute();
    }

    private function storedRowCount(): int
    {
        return (int) (new Query($this->db))->from('settings')->count();
    }
}
