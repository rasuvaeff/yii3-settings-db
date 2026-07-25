<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-settings-db' => [
        // one source of truth: both DbSettingsProvider and the bundled migration
        // read the resulting name through SettingsTableName
        'table' => 'settings',
        // prepended to `table`; set it once to keep every rasuvaeff table out
        // of the way of your application's own
        'table_prefix' => '',
        'cipher' => [
            'key_id' => 'main',
            // 32-byte key, base64 (SODIUM_BASE64_VARIANT_ORIGINAL). Null disables encryption;
            // required when any secret definition exists.
            'key' => null,
        ],
    ],
];
