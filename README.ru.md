# rasuvaeff/yii3-settings-db
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-settings-db.svg?label=stable)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-settings-db.svg)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![Static Analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-settings-db/static-analysis.yml?branch=master&label=psalm)](https://github.com/rasuvaeff/yii3-settings-db/actions)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-settings-db/php)](https://packagist.org/packages/rasuvaeff/yii3-settings-db)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-settings-db.svg)](LICENSE.md)
Поставщик записываемых настроек на основе базы данных для приложений Yii3. Реализует WritableSettingsProvider и SettingsInspector из rasuvaeff/yii3-settings, сохраняет переопределения времени выполнения в таблице БД и поддерживает шифрование секретных настроек при хранении с помощью libsodium (XChaCha20-Poly1305).

 > Используете помощника по программированию с искусственным интеллектом? [llms.txt](llms.txt) содержит компактную ссылку на API, которую можно использовать в модели. @@ЛИНИЯ@@
## Требования
- PHP 8.3+
 - `ext-sodium` (в комплекте с PHP 7.2+)
 - `rasuvaeff/yii3-settings` ^1.0
 - `yiisoft/db` ^2.0
 - `yiisoft/db-migration` ^2.0
 - реализация кэша PSR-16 — требуется транзитивно `yiisoft/db` 2.0
 (например, `yiisoft/cache`)

## Установка
```bash
composer require rasuvaeff/yii3-settings-db
```
Требуется `rasuvaeff/yii3-settings` ^1.0. С помощью плагина конфигурации Yii3 этот пакет автоматически связывает
 `SettingsProvider`, `WritableSettingsProvider` ** и `SettingsInspector`**
 (все они разрешаются к одному и тому же экземпляру `DbSettingsProvider`); ядро
 связывает фасад «Настройки». **Не** также связывайте их в своем приложении или
 в другом бэкэнде, иначе `yiisoft/config` сообщит об ошибке `Дублированный ключ`. @@ЛИНИЯ@@
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
Конструктор принимает необязательный резервный вариант (SettingsProvider). Когда ключ
 не имеет сохраненной строки БД, `get()` делегирует резервный вариант (который должен распознавать те же ключи
) вместо возврата определения по умолчанию — именно так конфигурационные значения
 сохраняются в проводке DI ниже. Без резервного варианта отсутствующая строка
 разрешается в `SettingDefinition::default`. @@ЛИНИЯ@@
### Подключение плагина конфигурации Yii3
Пакет поставляется с `config/params.php` и `config/di.php` через config-plugin.
 Он связывает `WritableSettingsProvider`, `SettingsProvider` и `SettingsInspector`;
 сам фасад `Settings` связан с ядром (`rasuvaeff/yii3-settings` ^1.0)
 из внедренного `SettingsProvider`. Привязка по умолчанию обеспечивает работу явных `значений` конфигурации
: `DbSettingsProvider` построен с резервным вариантом `ConfigSettingsProvider`
, поэтому ключ без сохраненной строки БД разрешается в свое `значение` конфигурации (и только тогда
 в определение по умолчанию).

 Шифрование готово: установите `rasuvaeff/yii3-settings-db.cipher.key` (32-байтовый ключ,
 base64), и связка создаст `KeyRing` + `SodiumCipher` для вас — никакой ручной криптографической проводки
 в главном приложении. Если существует какое-либо секретное определение, требуется ключ
 (в противном случае поставщик выдает ошибку при построении). @@ЛИНИЯ@@
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
Приоритет выполнения после подключения:

 | Источник | Приоритет |
 |---|---|
 | строка БД | 1 |
 | Явные `значения` конфигурации | 2 |
 | `SettingDefinition::default` | 3 | @@ЛИНИЯ@@
### Миграция
Зарегистрируйте путь миграции в конфигурации вашего приложения:
.
```php
return [
    'yiisoft/db-migration' => [
        'sourcePaths' => [
            dirname(__DIR__) . '/vendor/rasuvaeff/yii3-settings-db/migrations',
        ],
    ],
];
```
Затем запустите:

```bash
./yii migrate:up
./yii migrate:down --limit=1
```
### Декоратор основного кэша
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
### Инспектор настроек
```php
$state = $provider->describe(key: 'billing.stripe_key');
$state->key;               // 'billing.stripe_key'
$state->hasStoredOverride; // true
$state->source;            // 'db', 'config', or 'default'
$state->isSecret;          // true — value is masked (null)
$state->isWritable;        // true
```
### Ключевое вращение
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
`getByPrefix()` запускает один запрос `LIKE`, затем разрешает каждый ключ через
 с обычным приоритетом (строка БД > резервный вариант > по умолчанию). `setMany()` заранее проверяет все ключи
 (неизвестно → `UnknownSettingException`, только чтение →
 `ReadonlySettingException`), затем выполняет повторную отправку в транзакции — если какая-либо запись завершается неудачей
, весь пакет откатывается. @@ЛИНИЯ@@
### Консольные команды
```bash
# Re-encrypt all secret values with the current active key
./yii settings:reencrypt
```
Команда `settings:reencrypt` запускает `reencryptSecrets()` на активном экземпляре
 `DbSettingsProvider`. Работает в любом приложении Symfony Console /
 `yiisoft/yii-console`. Класс `ReencryptSettingsCommand`
 автоматически подключается через DI. @@ЛИНИЯ@@
### Публичный API
| Класс | Описание |
 |---|---|
 | `DbSettingsProvider` | `WritableSettingsProvider` +`SettingsInspector` на базе БД |
 | `Брелок` | Управление ключами с контролем версий и активным ключом |
 | `SodiumCipher` | Шифрование XChaCha20-Poly1305 с помощью libsodium |
 | `ReencryptSettingsCommand` | Консольная команда (`settings:reencrypt`) |
 | `InvalidSettingRowException` | Несоответствие типа сохраненной строки | @@ЛИНИЯ@@
## Безопасность
- Неизвестные ключи отклоняются: `get()`, `set()` и `remove()` выбрасывают `UnknownSettingException`.
 - значения БД нормализуются через `SettingDefinition`; неверные целые числа/числа с плавающей запятой/массивы выдают `InvalidSettingRowException`.
 — пользовательские значения проходят через связанные параметры в командах записи/удаления.
 - Имена таблиц должны соответствовать конфигурации доверенного приложения.
 - **Шифрование при хранении**: XChaCha20-Poly1305 AEAD через libsodium. Для каждого значения используется случайный 24-байтовый одноразовый номер.
 - **Привязка AAD**: зашифрованный текст привязан к ключу настройки — значение нельзя переместить в другую строку.
 - **Громкий сбой**: подделанные данные выбрасывают `DecryptionException`, никогда не отключая молчание.
 - **Ротация ключей**: идентификатор ключа в конверте позволяет читать старым ключом, писать активным ключом.
 - **Секретные значения** не отображаются в `SettingState::efficientValue` (замаскированы под ноль).
 — Шифр ​​требуется, когда существуют определения `secret: true` — быстрое создание. @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) для работоспособных сценариев. @@ЛИНИЯ@@
## Разработка
```bash
make install && make build
```
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
