<?php

declare(strict_types=1);

use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Creates the settings table read by {@see \Rasuvaeff\Yii3SettingsDb\DbSettingsProvider}.
 *
 * The table name defaults to `settings` and must match the `table` argument of
 * {@see \Rasuvaeff\Yii3SettingsDb\DbSettingsProvider}. To use a custom name,
 * bind the constructor argument in your DI configuration:
 *
 * ```php
 * M260605120000CreateSettingsTable::class => [
 *     '__construct()' => ['table' => 'my_settings'],
 * ],
 * ```
 */
final class M260605120000CreateSettingsTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    /**
     * @param non-empty-string $table
     */
    public function __construct(
        private readonly string $table = 'settings',
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $b->createTable(
            $this->table,
            [
                'key' => 'string(190) NOT NULL PRIMARY KEY',
                'value' => 'text NOT NULL',
            ],
        );
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable($this->table);
    }
}
