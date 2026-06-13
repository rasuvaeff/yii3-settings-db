<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Settings\ConfigSettingsProvider;
use Rasuvaeff\Yii3Settings\SettingsInspector;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\WritableSettingsProvider;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;
use Rasuvaeff\Yii3SettingsDb\Crypto\SodiumCipher;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

/** @var array $params */

$settingsParams = $params['rasuvaeff/yii3-settings'] ?? [];
$dbParams = $params['rasuvaeff/yii3-settings-db'] ?? [];
$definitions = $settingsParams['definitions'] ?? [];

$cipher = null;
$cipherConfig = $dbParams['cipher'] ?? [];
if (is_array($cipherConfig) && !empty($cipherConfig['key'])) {
    $keyId = (string) ($cipherConfig['key_id'] ?? 'main');
    $keyRing = new KeyRing(
        keys: [$keyId => sodium_base642bin((string) $cipherConfig['key'], SODIUM_BASE64_VARIANT_ORIGINAL)],
        activeKeyId: $keyId,
    );
    $cipher = new SodiumCipher(keyRing: $keyRing);
}

return [
    WritableSettingsProvider::class => static fn (ConnectionInterface $db) => new DbSettingsProvider(
        db: $db,
        definitions: $definitions,
        table: $dbParams['table'] ?? 'settings',
        fallback: new ConfigSettingsProvider(
            definitions: $definitions,
            values: $settingsParams['values'] ?? [],
        ),
        cipher: $cipher,
    ),
    SettingsProvider::class => WritableSettingsProvider::class,
    SettingsInspector::class => WritableSettingsProvider::class,
];
