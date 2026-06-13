<?php

declare(strict_types=1);

return [
    'rasuvaeff/yii3-settings-db' => [
        'table' => 'settings',
        'cipher' => [
            'key_id' => 'main',
            // 32-byte key, base64 (SODIUM_BASE64_VARIANT_ORIGINAL). Null disables encryption;
            // required when any secret definition exists.
            'key' => null,
        ],
    ],
];
