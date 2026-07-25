<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Migration;

use Rasuvaeff\Yii3SettingsDb\SettingsTableName;
use Yiisoft\Db\Migration\MigrationBuilder;
use Yiisoft\Db\Migration\RevertibleMigrationInterface;
use Yiisoft\Db\Migration\TransactionalMigrationInterface;

/**
 * Creates the settings table read by {@see \Rasuvaeff\Yii3SettingsDb\DbSettingsProvider}.
 *
 * The table name comes from {@see SettingsTableName}, which `config/di.php`
 * builds from params — one source of truth for the migration and the
 * runtime code alike. Register the migration by namespace:
 *
 * ```php
 * MigrationService::class => [
 *     'setSourceNamespaces()' => [['Rasuvaeff\\Yii3SettingsDb\\Migration']],
 * ],
 * ```
 *
 * @api
 */
final class M260605120000CreateSettingsTable implements RevertibleMigrationInterface, TransactionalMigrationInterface
{
    public function __construct(
        private readonly SettingsTableName $table = new SettingsTableName(),
    ) {}

    #[\Override]
    public function up(MigrationBuilder $b): void
    {
        $b->createTable(
            $this->table->value,
            [
                'key' => 'string(190) NOT NULL PRIMARY KEY',
                'value' => 'text NOT NULL',
            ],
        );
    }

    #[\Override]
    public function down(MigrationBuilder $b): void
    {
        $b->dropTable($this->table->value);
    }
}
