# rasuvaeff/yii3-settings-db

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-settings-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-settings-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/static-analysis.yml?branch=master&label=psalm)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![Coverage](https://codecov.io/gh/rasuvaeff/yii3-settings-db/branch/master/graph/badge.svg)](https://codecov.io/gh/rasuvaeff/yii3-settings-db)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-settings-db/php)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-settings-db.svg)](LICENSE.md)

Database-backed writable settings provider for Yii3 applications. Implements `WritableSettingsProvider` from `rasuvaeff/yii3-settings`, persists runtime overrides in a DB table, and preserves config `values` via `ChainSettingsProvider([Db, Config])` in Yii3 DI wiring.

> Using an AI coding assistant? [llms.txt](llms.txt) has a compact API reference you can feed into the model.

## Requirements

- PHP 8.3+
- `rasuvaeff/yii3-settings` ^1.0
- `yiisoft/db` ^1.2
- `yiisoft/db-migration` ^1.2

## Installation

```bash
composer require rasuvaeff/yii3-settings-db
```

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

### Yii3 config-plugin wiring

The package ships `config/params.php` and `config/di.php` via config-plugin.
The default wiring keeps explicit config `values` working by chaining DB first and config second.

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

### Public API

| Class | Description |
|---|---|
| `DbSettingsProvider` | DB-backed implementation of `WritableSettingsProvider` |
| `InvalidSettingRowException` | Thrown when a stored row cannot be converted to the declared setting type |

## Security

- Unknown keys are rejected: `get()`, `set()`, and `remove()` throw `UnknownSettingException` if the key is not declared in `definitions`.
- DB values are normalized through the declared `SettingDefinition`; malformed stored ints/floats/arrays throw `InvalidSettingRowException` instead of silently coercing invalid payloads.
- User values go through bound parameters in write/delete commands.
- Table names must be trusted application configuration; do not pass user input as the `table` argument.
- Raw SQL in tests/examples quotes `"key"` and `"value"` because they are reserved words.

## Examples

See [examples/](examples/) for runnable scripts.

## Development

```bash
make install && make build
```

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
