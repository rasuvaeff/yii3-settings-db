# rasuvaeff/yii3-settings-db

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-settings-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-settings-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/static-analysis.yml?branch=master&label=psalm)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-settings-db/php)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-settings-db.svg)](LICENSE.md)

Database-backed writable settings provider for Yii3 applications. Implements `WritableSettingsProvider` and `SettingsInspector` from `rasuvaeff/yii3-settings`, persists runtime overrides in a DB table, and supports at-rest encryption of secret settings via libsodium (XChaCha20-Poly1305).

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference you can feed into the model.

## Requirements

- PHP 8.3+
- `ext-sodium` (bundled with PHP 7.2+)
- `rasuvaeff/yii3-settings` ^1.1
- `yiisoft/db` ^2.0
- `yiisoft/db-migration` ^2.0
- a PSR-16 cache implementation — required transitively by `yiisoft/db` 2.0
  (e.g. `yiisoft/cache`)

## Installation

```bash
composer require rasuvaeff/yii3-settings-db
```

Requires `rasuvaeff/yii3-settings` ^2.0. With Yii3 config-plugin this package binds
`SettingsProvider` (and `WritableSettingsProvider`) automatically; the core binds
the `Settings` facade. Do **not** also bind `SettingsProvider` in your application
or another backend, or `yiisoft/config` reports a `Duplicate key` error.

## Usage

### Basic provider

```php
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3Settings\Settings;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;

$definitions = [
    'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
    'orders.max_items' => new SettingDefinition(key: 'orders.max_items', type: SettingType::Int, default: 100),
];

$provider = new DbSettingsProvider(
    db: $connection,
    definitions: $definitions,
    table: 'settings',
);

$provider->set('mail.from', 'admin@example.com');
$provider->set('orders.max_items', '250');

$settings = new Settings(provider: $provider, definitions: $definitions);

$settings->string('mail.from');
$settings->int('orders.max_items');
```

The constructor accepts an optional `fallback` (`SettingsProvider`). When a key
has no stored DB row, `get()` delegates to the fallback (which must recognize the
same keys) instead of returning the definition default — this is how config
`values` are preserved in the DI wiring below. Without a fallback, a missing row
resolves to `SettingDefinition::default`.

### Yii3 config-plugin wiring

The package ships `config/params.php` and `config/di.php` via config-plugin.
It binds `WritableSettingsProvider` and `SettingsProvider`; the `Settings` facade
itself is bound by the core (`rasuvaeff/yii3-settings` ^2.0) from the injected
`SettingsProvider`. The default wiring keeps explicit config `values` working:
`DbSettingsProvider` is built with a `ConfigSettingsProvider` fallback, so a key
without a stored DB row resolves to its config `value` (and only then to the
definition default).

```php
return [
    'rasuvaeff/yii3-settings' => [
        'definitions' => [
            'mail.from' => ['type' => 'string', 'default' => 'noreply@example.com'],
            'orders.max_items' => ['type' => 'int', 'default' => 100],
        ],
        'values' => [
            'mail.from' => 'config@example.com',
        ],
    ],
    'rasuvaeff/yii3-settings-db' => [
        'table' => 'settings',
    ],
];
```

Runtime precedence after wiring:

| Source | Priority |
|---|---|
| DB row | 1 |
| Explicit config `values` | 2 |
| `SettingDefinition::default` | 3 |

### Migration

Register the shipped migration path in your app config:

```php
return [
    'yiisoft/db-migration' => [
        'sourcePaths' => [
            dirname(__DIR__) . '/vendor/rasuvaeff/yii3-settings-db/migrations',
        ],
    ],
];
```

Then run:

```bash
./yii migrate:up
./yii migrate:down --limit=1
```

### Core cache decorator

```php
use Rasuvaeff\Yii3Settings\CachedSettingsProvider;

$cached = new CachedSettingsProvider(
    inner: $provider,
    cache: $psr16Cache,
    definitions: $definitions,
    ttl: 60,
);

$cached->get('mail.from');
$cached->clear('mail.from');
```

### Secret settings

```php
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;
use Rasuvaeff\Yii3SettingsDb\Crypto\SodiumCipher;

$keyRing = new KeyRing(
    keys: ['key-2025' => sodium_crypto_aead_xchacha20poly1305_ietf_keygen()],
    activeKeyId: 'key-2025',
);
$cipher = new SodiumCipher(keyRing: $keyRing);

$definitions = [
    'billing.stripe_key' => new SettingDefinition(key: 'billing.stripe_key', type: SettingType::String, secret: true),
    'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
];

$provider = new DbSettingsProvider(db: $db, definitions: $definitions, cipher: $cipher);

$provider->set('billing.stripe_key', 'sk_live_xxx');
// Stored as enc:vkey-2025:... in DB — plaintext never at rest.

$provider->get('billing.stripe_key'); // 'sk_live_xxx' (transparent decrypt)
```

### Settings inspector

```php
$state = $provider->describe(key: 'billing.stripe_key');
$state->key;               // 'billing.stripe_key'
$state->hasStoredOverride; // true
$state->source;            // 'db', 'config', or 'default'
$state->isSecret;          // true — value is masked (null)
$state->isWritable;        // true
```

### Key rotation

```php
$count = $provider->reencryptSecrets();
// Decrypts all stored secret values with their current key,
// re-encrypts with the active key. Plaintext values (without enc: prefix)
// are encrypted in-place. Returns count of re-encrypted keys.
```

### Public API

| Class | Description |
|---|---|
| `DbSettingsProvider` | DB-backed `WritableSettingsProvider` + `SettingsInspector` |
| `KeyRing` | Key management with versioning and active key |
| `SodiumCipher` | XChaCha20-Poly1305 encryption via libsodium |
| `InvalidSettingRowException` | Stored row type mismatch |

## Security

- Unknown keys are rejected: `get()`, `set()`, and `remove()` throw `UnknownSettingException`.
- DB values are normalized through `SettingDefinition`; malformed ints/floats/arrays throw `InvalidSettingRowException`.
- User values go through bound parameters in write/delete commands.
- Table names must be trusted application configuration.
- **At-rest encryption**: XChaCha20-Poly1305 AEAD via libsodium. Each value uses a random 24-byte nonce.
- **AAD binding**: ciphertext is bound to the setting key — a value cannot be moved to a different row.
- **Fail loud**: tampered data throws `DecryptionException`, never silent fallback.
- **Key rotation**: keyId in envelope allows reading with old key, writing with active key.
- **Secret values** do not appear in `SettingState::effectiveValue` (masked as null).
- Cipher is required when `secret: true` definitions exist — fail-fast at construction.

## Examples

See [examples/](examples/) for runnable scripts.

## Development

```bash
make install && make build
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
