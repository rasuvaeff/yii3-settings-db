<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Settings\Settings;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;

require __DIR__ . '/bootstrap.php';

$db = exampleConnection();
$definitions = exampleDefinitions();

$provider = new DbSettingsProvider(
    db: $db,
    definitions: $definitions,
);

$provider->set('mail.from', 'admin@example.com');
$provider->set('orders.max_items', '250');

$settings = new Settings(provider: $provider, definitions: $definitions);

echo $settings->string('mail.from') . PHP_EOL;
echo (string) $settings->int('orders.max_items') . PHP_EOL;
