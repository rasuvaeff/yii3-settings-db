<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Settings\ConfigSettingsProvider;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\WritableSettingsProvider;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var array $params */

return [
    WritableSettingsProvider::class => static fn (ConnectionInterface $db) => new DbSettingsProvider(
        db: $db,
        definitions: $params['rasuvaeff/yii3-settings']['definitions'] ?? [],
        table: ($params['rasuvaeff/yii3-settings-db'] ?? [])['table'] ?? 'settings',
        fallback: new ConfigSettingsProvider(
            definitions: $params['rasuvaeff/yii3-settings']['definitions'] ?? [],
            values: $params['rasuvaeff/yii3-settings']['values'] ?? [],
        ),
    ),
    SettingsProvider::class => static fn (WritableSettingsProvider $provider): SettingsProvider => $provider,
];
