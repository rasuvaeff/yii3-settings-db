# rasuvaeff/yii3-settings-db

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-settings-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-settings-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/static-analysis.yml?branch=master&label=psalm)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-settings-db/php)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-settings-db.svg)](LICENSE.md)
[English version](README.md)

БД-бэкенд для перезаписываемого settings-провайдера в Yii3-приложениях.
Реализует `WritableSettingsProvider` и `SettingsInspector` из
`rasuvaeff/yii3-settings`, сохраняет runtime-переопределения в таблице БД и
поддерживает шифрование секретных настроек at-rest через libsodium
(XChaCha20-Poly1305).

> Используете AI-ассистента? В [llms.txt](llms.txt) — компактный API-справочник,
> который можно загрузить в модель.

## Требования

- PHP 8.3+
- `ext-sodium` (входит в состав PHP 7.2+)
- `rasuvaeff/yii3-settings` ^1.0
- `yiisoft/db` ^2.0
- `yiisoft/db-migration` ^2.0
- реализация PSR-16 cache — транзитивно требуется `yiisoft/db` 2.0
  (например, `yiisoft/cache`)

## Установка

```bash
composer require rasuvaeff/yii3-settings-db
```

Требуется `rasuvaeff/yii3-settings` ^1.0. С Yii3 config-plugin этот пакет
привязывает `SettingsProvider`, `WritableSettingsProvider` **и `SettingsInspector`**
автоматически (все разрешаются в один и тот же экземпляр `DbSettingsProvider`);
ядро привязывает фасад `Settings`. **Не** привязывайте их также в приложении
или другом бэкенде — иначе `yiisoft/config` сообщит об ошибке `Duplicate key`.

## Использование

### Базовый провайдер

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

Конструктор принимает опциональный `fallback` (`SettingsProvider`). Если у ключа
нет сохранённой строки в БД, `get()` делегирует к fallback-провайдеру (он должен
распознавать те же ключи), а не возвращает default из определения — именно так
конфигурационные `values` сохраняются в DI-связке ниже. Без fallback
отсутствующая строка разрешается в `SettingDefinition::default`.

### Подключение через Yii3 config-plugin

Пакет поставляет `config/params.php` и `config/di.php` через config-plugin.
Он привязывает `WritableSettingsProvider`, `SettingsProvider` и
`SettingsInspector`; фасад `Settings` привязывается ядром
(`rasuvaeff/yii3-settings` ^1.0) из инжектированного `SettingsProvider`. Связка
по умолчанию сохраняет работоспособность явных конфигурационных `values`:
`DbSettingsProvider` строится с fallback `ConfigSettingsProvider`, поэтому ключ
без сохранённой строки в БД разрешается в своё конфигурационное `value` (и только
затем — в default определения).

Шифрование идёт «из коробки»: задайте
`rasuvaeff/yii3-settings-db.cipher.key` (32-байтовый ключ, base64), и связка
соберёт `KeyRing` + `SodiumCipher` за вас — без ручной крипто-связки в
хост-приложении. При наличии хотя бы одного секретного определения ключ
обязателен (иначе конструктор провайдера бросает исключение).

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
        'cipher' => [
            'key_id' => 'main',
            'key' => $env['SETTINGS_CIPHER_KEY'] ?? null, // 32-byte key, base64
        ],
    ],
];
```

Runtime-приоритет после связки:

| Источник | Приоритет |
|---|---|
| Строка БД | 1 |
| Явные конфигурационные `values` | 2 |
| `SettingDefinition::default` | 3 |

### Миграция

Зарегистрируйте поставляемый путь миграций в конфиге приложения:

```php
return [
    'yiisoft/db-migration' => [
        'sourcePaths' => [
            dirname(__DIR__) . '/vendor/rasuvaeff/yii3-settings-db/migrations',
        ],
    ],
];
```

Затем выполните:

```bash
./yii migrate:up
./yii migrate:down --limit=1
```

### Кэш-декоратор ядра

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

### Секретные настройки

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

### Инспекция настроек

```php
$state = $provider->describe(key: 'billing.stripe_key');
$state->key;               // 'billing.stripe_key'
$state->hasStoredOverride; // true
$state->source;            // 'db', 'config', or 'default'
$state->isSecret;          // true — value is masked (null)
$state->isWritable;        // true
```

### Ротация ключей

```php
$count = $provider->reencryptSecrets();
// Decrypts all stored secret values with their current key,
// re-encrypts with the active key. Plaintext values (without enc: prefix)
// are encrypted in-place. Returns count of re-encrypted keys.
```

### Массовые операции

```php
// Resolve effective values for all keys starting with "mail."
$values = $provider->getByPrefix('mail.');
// ['mail.from' => 'admin@example.com', 'mail.enabled' => true]

// Set multiple values in a single transaction
$provider->setMany([
    'mail.from' => 'admin@example.com',
    'orders.max_items' => '250',
    'mail.enabled' => false,
]);
```

`getByPrefix()` выполняет один `LIKE`-запрос, затем разрешает каждый ключ по
обычному приоритету (строка БД > fallback > default). `setMany()` заранее
валидирует все ключи (неизвестный → `UnknownSettingException`, readonly →
`ReadonlySettingException`), затем делает upsert в транзакции — если любая
запись падает, откатывается весь батч.

### Консольные команды

```bash
# Re-encrypt all secret values with the current active key
./yii settings:reencrypt
```

Команда `settings:reencrypt` запускает `reencryptSecrets()` на активном
экземпляре `DbSettingsProvider`. Работает в любом приложении на Symfony
Console / `yiisoft/yii-console`. Класс `ReencryptSettingsCommand`
автосвязывается через DI.

### Публичный API

| Класс | Описание |
|---|---|
| `DbSettingsProvider` | БД-бэкенд `WritableSettingsProvider` + `SettingsInspector` |
| `KeyRing` | Управление ключами с версионированием и активным ключом |
| `SodiumCipher` | Шифрование XChaCha20-Poly1305 через libsodium |
| `ReencryptSettingsCommand` | Консольная команда (`settings:reencrypt`) |
| `InvalidSettingRowException` | Несовпадение типа в сохранённой строке |

## Безопасность

- Неизвестные ключи отклоняются: `get()`, `set()` и `remove()` бросают `UnknownSettingException`.
- Значения БД нормализуются через `SettingDefinition`; некорректные int/float/array бросают `InvalidSettingRowException`.
- Пользовательские значения проходят через bound parameters в write/delete-командах.
- Имена таблиц должны быть доверенной конфигурацией приложения.
- **Шифрование at-rest**: XChaCha20-Poly1305 AEAD через libsodium. Для каждого значения используется случайный 24-байтовый nonce.
- **Привязка AAD**: шифртекст привязан к ключу настройки — значение нельзя перенести в другую строку.
- **Fail loud**: подделанные данные бросают `DecryptionException`, без молчаливого фоллбэка.
- **Ротация ключей**: keyId в конверте позволяет чтение старым ключом и запись активным.
- **Секретные значения** не появляются в `SettingState::effectiveValue` (маскируются как `null`).
- Шифр обязателен при наличии `secret: true`-определений — fail-fast в конструкторе.

## Примеры

См. [examples/](examples/) — запускаемые скрипты.

## Разработка

```bash
make install && make build
```

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
