<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Settings\Settings;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\WritableSettingsProvider;

require dirname(__DIR__) . '/vendor/autoload.php';

$params = [
    'rasuvaeff/yii3-settings' => [
        'definitions' => [
            'mail.from' => ['type' => 'string', 'default' => 'noreply@example.com'],
            'orders.max_items' => ['type' => 'int', 'default' => 100],
        ],
        'values' => [
            'mail.from' => 'config@example.com',
        ],
        'strictMode' => false,
    ],
    'rasuvaeff/yii3-settings-db' => [
        'table' => 'settings',
    ],
    'yiisoft/db-migration' => [
        'sourcePaths' => [
            dirname(__DIR__) . '/migrations',
        ],
    ],
];

echo WritableSettingsProvider::class . PHP_EOL;
echo SettingsProvider::class . PHP_EOL;
echo Settings::class . PHP_EOL;
print_r($params);
