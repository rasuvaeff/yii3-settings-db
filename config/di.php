<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Settings\ChainSettingsProvider;
use Rasuvaeff\Yii3Settings\ConfigSettingsProvider;
use Rasuvaeff\Yii3Settings\Settings;
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
    ),
    SettingsProvider::class => static function (WritableSettingsProvider $dbProvider) use ($params): SettingsProvider {
        $configProvider = new ConfigSettingsProvider(
            definitions: $params['rasuvaeff/yii3-settings']['definitions'] ?? [],
            values: $params['rasuvaeff/yii3-settings']['values'] ?? [],
        );

        return new ChainSettingsProvider(providers: [$dbProvider, $configProvider]);
    },
    Settings::class => static fn (SettingsProvider $provider) => new Settings(
        provider: $provider,
        definitions: ConfigSettingsProvider::normalizeDefinitions(
            $params['rasuvaeff/yii3-settings']['definitions'] ?? [],
        ),
        strictMode: $params['rasuvaeff/yii3-settings']['strictMode'] ?? false,
    ),
];
