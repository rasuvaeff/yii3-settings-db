<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration;

use InvalidArgumentException;
use Rasuvaeff\Yii3Settings\CachedSettingsProvider;
use Rasuvaeff\Yii3Settings\ConfigSettingsProvider;
use Rasuvaeff\Yii3Settings\Crypto\Cipher;
use Rasuvaeff\Yii3Settings\Exception\ReadonlySettingException;
use Rasuvaeff\Yii3Settings\Exception\UnknownSettingException;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingsInspector;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;
use Rasuvaeff\Yii3SettingsDb\Crypto\SodiumCipher;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Rasuvaeff\Yii3SettingsDb\Exception\InvalidSettingRowException;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[Covers(DbSettingsProvider::class)]
final class SqliteIntegrationTest
{
    private ConnectionInterface $db;

    /**
     * @var array<string, SettingDefinition>
     */
    private array $definitions;

    #[BeforeTest]
    public function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new MemorySimpleCache());
        $this->db = new SqliteConnection(driver: $driver, schemaCache: $schemaCache);
        $this->db->open();

        $this->db->createCommand(sql: 'DROP TABLE IF EXISTS settings')->execute();
        $this->db->createCommand(sql: 'CREATE TABLE settings ("key" VARCHAR(190) PRIMARY KEY, "value" TEXT NOT NULL)')->execute();

        $this->definitions = [
            'mail.from' => new SettingDefinition(key: 'mail.from', type: SettingType::String, default: 'noreply@example.com'),
            'orders.max_items' => new SettingDefinition(key: 'orders.max_items', type: SettingType::Int, default: 100),
            'mail.enabled' => new SettingDefinition(key: 'mail.enabled', type: SettingType::Bool, default: true),
            'vat.rate' => new SettingDefinition(key: 'vat.rate', type: SettingType::Float, default: 20.0),
            'app.features' => new SettingDefinition(key: 'app.features', type: SettingType::Array, default: ['search' => true]),
        ];
    }

    #[AfterTest]
    public function tearDown(): void
    {
        $this->db->close();
    }

    public function hasReturnsTrueForDefinedKeyWithoutStoredRow(): void
    {
        $provider = $this->provider();

        Assert::true($provider->has('mail.from'));
    }

    public function hasReturnsFalseForUnknownKey(): void
    {
        $provider = $this->provider();

        Assert::false($provider->has('unknown'));
    }

    public function getReturnsDefaultWhenNoStoredRowExists(): void
    {
        $provider = $this->provider();

        Assert::same($provider->get('mail.from'), 'noreply@example.com');
    }

    public function setAndGetStringValue(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');

        Assert::same($provider->get('mail.from'), 'admin@example.com');
    }

    public function setAndGetIntValue(): void
    {
        $provider = $this->provider();
        $provider->set('orders.max_items', '250');

        Assert::same($provider->get('orders.max_items'), 250);
    }

    public function setAndGetBoolValue(): void
    {
        $provider = $this->provider();
        $provider->set('mail.enabled', false);

        Assert::false($provider->get('mail.enabled'));
    }

    public function setAndGetFloatValue(): void
    {
        $provider = $this->provider();
        $provider->set('vat.rate', '21.5');

        Assert::same($provider->get('vat.rate'), 21.5);
    }

    public function setAndGetArrayValue(): void
    {
        $provider = $this->provider();
        $provider->set('app.features', ['search' => true, 'beta' => ['a', 'b']]);

        Assert::same($provider->get('app.features'), ['search' => true, 'beta' => ['a', 'b']]);
    }

    public function getReturnsFallbackValueWhenNoStoredRow(): void
    {
        $provider = $this->provider(fallback: new ConfigSettingsProvider(
            definitions: $this->definitions,
            values: ['mail.from' => 'config@example.com'],
        ));

        Assert::same($provider->get('mail.from'), 'config@example.com');
    }

    public function getFallsBackToDefinitionDefaultWhenFallbackHasNoValue(): void
    {
        $provider = $this->provider(fallback: new ConfigSettingsProvider(
            definitions: $this->definitions,
            values: [],
        ));

        Assert::same($provider->get('mail.from'), 'noreply@example.com');
    }

    public function storedRowTakesPrecedenceOverFallback(): void
    {
        $provider = $this->provider(fallback: new ConfigSettingsProvider(
            definitions: $this->definitions,
            values: ['mail.from' => 'config@example.com'],
        ));
        $provider->set('mail.from', 'db@example.com');

        Assert::same($provider->get('mail.from'), 'db@example.com');
    }

    public function getReadsRequestedKeyWhenAnotherRowExists(): void
    {
        $this->insertRawRow(key: 'mail.from', value: 'admin@example.com');
        $this->insertRawRow(key: 'orders.max_items', value: '250');
        $provider = $this->provider();

        Assert::same($provider->get('mail.from'), 'admin@example.com');
        Assert::same($provider->get('orders.max_items'), 250);
    }

    public function removeDeletesStoredValueAndFallsBackToDefault(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');
        $provider->remove('mail.from');

        Assert::same($provider->get('mail.from'), 'noreply@example.com');
    }

    public function removeDeletesOnlyRequestedRow(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');
        $provider->set('orders.max_items', 250);

        $provider->remove('mail.from');

        Assert::same($provider->get('mail.from'), 'noreply@example.com');
        Assert::same($provider->get('orders.max_items'), 250);
    }

    public function removeMissingStoredRowIsNoOp(): void
    {
        $provider = $this->provider();
        $provider->remove('mail.from');

        Assert::same($provider->get('mail.from'), 'noreply@example.com');
    }

    public function setOverwritesExistingValue(): void
    {
        $provider = $this->provider();
        $provider->set('orders.max_items', 100);
        $provider->set('orders.max_items', 200);

        Assert::same($provider->get('orders.max_items'), 200);
    }

    public function throwsOnUnknownKeyGet(): void
    {
        $provider = $this->provider();

        try {
            $provider->get('unknown');
            Assert::fail('Expected UnknownSettingException');
        } catch (UnknownSettingException $e) {
            Assert::string($e->getMessage())->contains('Unknown setting "unknown"');
        }
    }

    public function throwsOnUnknownKeySet(): void
    {
        $provider = $this->provider();

        try {
            $provider->set('unknown', 'value');
            Assert::fail('Expected UnknownSettingException');
        } catch (UnknownSettingException $e) {
            Assert::string($e->getMessage())->contains('Unknown setting "unknown"');
        }
    }

    public function throwsOnUnknownKeyRemove(): void
    {
        $provider = $this->provider();

        try {
            $provider->remove('unknown');
            Assert::fail('Expected UnknownSettingException');
        } catch (UnknownSettingException $e) {
            Assert::string($e->getMessage())->contains('Unknown setting "unknown"');
        }
    }

    public function throwsOnInvalidStoredInt(): void
    {
        $this->insertRawRow(key: 'orders.max_items', value: 'abc');
        $provider = $this->provider();

        try {
            $provider->get('orders.max_items');
            Assert::fail('Expected InvalidSettingRowException');
        } catch (InvalidSettingRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid stored int for setting "orders.max_items"');
        }
    }

    public function throwsOnInvalidStoredJson(): void
    {
        $this->insertRawRow(key: 'app.features', value: 'not-json');
        $provider = $this->provider();

        try {
            $provider->get('app.features');
            Assert::fail('Expected InvalidSettingRowException');
        } catch (InvalidSettingRowException $e) {
            Assert::string($e->getMessage())->contains('Invalid stored JSON for setting "app.features"');
        }
    }

    public function integratesWithCoreCachedSettingsProvider(): void
    {
        $this->insertRawRow(key: 'mail.from', value: 'first@example.com');
        $provider = $this->provider();
        $cached = new CachedSettingsProvider(
            inner: $provider,
            cache: new MemorySimpleCache(),
            definitions: $this->definitions,
            ttl: 60,
        );

        Assert::same($cached->get('mail.from'), 'first@example.com');

        $this->insertOrReplaceRawRow(key: 'mail.from', value: 'second@example.com');

        Assert::same($cached->get('mail.from'), 'first@example.com');

        $cached->clear('mail.from');

        Assert::same($cached->get('mail.from'), 'second@example.com');
    }

    public function constructorThrowsWhenSecretDefWithoutCipher(): void
    {
        $defs = $this->definitionsWithSecret();

        try {
            new DbSettingsProvider(db: $this->db, definitions: $defs);
            Assert::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Cipher is required when secret definitions exist');
        }
    }

    public function secretSetAndGetRoundtrip(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_live_test123');

        Assert::same($provider->get('billing.stripe_key'), 'sk_live_test123');
    }

    public function secretValueIsEncryptedAtRest(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_live_test123');

        $stored = $this->queryStoredValue('billing.stripe_key');
        Assert::notNull($stored);
        Assert::true(str_starts_with($stored, 'enc:'));
        Assert::string($stored)->notContains('sk_live_test123');
    }

    public function secretGetReturnsDefaultWhenNoStoredRow(): void
    {
        $provider = $this->providerWithCipher();

        Assert::null($provider->get('billing.stripe_key'));
    }

    public function nonSecretKeysUnaffectedByCipher(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('mail.from', 'admin@example.com');

        $stored = $this->queryStoredValue('mail.from');
        Assert::notNull($stored);
        Assert::string($stored)->notContains('enc:');
        Assert::same($provider->get('mail.from'), 'admin@example.com');
    }

    public function describeForDbOverride(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('mail.from', 'admin@example.com');

        $state = $provider->describe(key: 'mail.from');

        Assert::same($state->key, 'mail.from');
        Assert::same($state->effectiveValue, 'admin@example.com');
        Assert::true($state->hasStoredOverride);
        Assert::same($state->source, 'db');
        Assert::false($state->isSecret);
        Assert::true($state->isWritable);
    }

    public function describeAllReturnsStateForEveryDefinition(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');

        $states = $provider->describeAll();

        Assert::count($states, \count($this->definitions));
        $keys = array_map(static fn($state): string => $state->key, $states);
        Assert::same($keys, array_keys($this->definitions));

        $byKey = [];
        foreach ($states as $state) {
            $byKey[$state->key] = $state;
        }
        Assert::same($byKey['mail.from']->source, 'db');
        Assert::same($byKey['orders.max_items']->source, 'default');
    }

    public function readonlyDefinitionIsNotWritable(): void
    {
        $provider = $this->readonlyProvider();

        $state = $provider->describe(key: 'app.version');

        Assert::false($state->isWritable);
    }

    public function setOnReadonlyThrows(): void
    {
        $provider = $this->readonlyProvider();

        Expect::exception(ReadonlySettingException::class);

        $provider->set('app.version', '2.0');
    }

    public function removeOnReadonlyThrows(): void
    {
        $provider = $this->readonlyProvider();

        Expect::exception(ReadonlySettingException::class);

        $provider->remove('app.version');
    }

    public function describeForConfigFallback(): void
    {
        $defs = $this->definitionsWithSecret();
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: $defs,
            fallback: new ConfigSettingsProvider(
                definitions: $defs,
                values: ['mail.from' => 'config@example.com'],
            ),
            cipher: new SodiumCipher(keyRing: new KeyRing(
                keys: ['key-2025' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)],
                activeKeyId: 'key-2025',
            )),
        );

        $state = $provider->describe(key: 'mail.from');

        Assert::same($state->source, 'config');
        Assert::same($state->effectiveValue, 'config@example.com');
        Assert::false($state->hasStoredOverride);
    }

    public function describeForDefaultSource(): void
    {
        $provider = $this->providerWithCipher();

        $state = $provider->describe(key: 'mail.from');

        Assert::same($state->source, 'default');
        Assert::same($state->effectiveValue, 'noreply@example.com');
        Assert::false($state->hasStoredOverride);
    }

    public function describeMasksSecretValue(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_live_test123');

        $state = $provider->describe(key: 'billing.stripe_key');

        Assert::true($state->isSecret);
        Assert::true($state->hasStoredOverride);
        Assert::same($state->source, 'db');
        Assert::null($state->effectiveValue);
    }

    public function describeSecretWithoutOverride(): void
    {
        $provider = $this->providerWithCipher();

        $state = $provider->describe(key: 'billing.stripe_key');

        Assert::true($state->isSecret);
        Assert::false($state->hasStoredOverride);
        Assert::null($state->effectiveValue);
    }

    public function reencryptSecretsEncryptsPlaintextValue(): void
    {
        $this->insertRawRow(key: 'billing.stripe_key', value: 'sk_old_plaintext');

        $provider = $this->providerWithCipher();
        $count = $provider->reencryptSecrets();

        Assert::same($count, 1);

        $stored = $this->queryStoredValue('billing.stripe_key');
        Assert::notNull($stored);
        Assert::true(str_starts_with($stored, 'enc:'));

        Assert::same($provider->get('billing.stripe_key'), 'sk_old_plaintext');
    }

    public function reencryptSecretsSkipsNonSecretKeys(): void
    {
        $this->insertRawRow(key: 'mail.from', value: 'admin@example.com');
        $this->insertRawRow(key: 'orders.max_items', value: '250');

        $provider = $this->providerWithCipher();
        $count = $provider->reencryptSecrets();

        Assert::same($count, 0);
    }

    public function reencryptSecretsSkipsAlreadyEncrypted(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_val');

        $count = $provider->reencryptSecrets();

        Assert::same($count, 0);
        Assert::same($provider->get('billing.stripe_key'), 'sk_val');
    }

    public function dbSettingsProviderImplementsInspector(): void
    {
        $provider = $this->providerWithCipher();

        Assert::instanceOf($provider, SettingsInspector::class);
    }

    public function getByPrefixReturnsDefaultsForNoStoredRows(): void
    {
        $provider = $this->provider();

        $result = $provider->getByPrefix('mail.');

        Assert::same($result['mail.from'], 'noreply@example.com');
        Assert::true($result['mail.enabled']);
    }

    public function getByPrefixReturnsDbOverrides(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');
        $provider->set('mail.enabled', false);

        $result = $provider->getByPrefix('mail.');

        Assert::same($result['mail.from'], 'admin@example.com');
        Assert::false($result['mail.enabled']);
    }

    public function getByPrefixResolvesFallback(): void
    {
        $provider = $this->provider(fallback: new ConfigSettingsProvider(
            definitions: $this->definitions,
            values: ['mail.from' => 'config@example.com'],
        ));

        $result = $provider->getByPrefix('mail.');

        Assert::same($result['mail.from'], 'config@example.com');
        Assert::true($result['mail.enabled']);
    }

    public function getByPrefixReturnsEmptyArrayForNoMatchingKeys(): void
    {
        $provider = $this->provider();

        $result = $provider->getByPrefix('nonexistent.');

        Assert::same($result, []);
    }

    public function getByPrefixDecryptsSecretValues(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_live_test123');

        $result = $provider->getByPrefix('billing.');

        Assert::same($result['billing.stripe_key'], 'sk_live_test123');
    }

    public function setManySetsMultipleValues(): void
    {
        $provider = $this->provider();
        $provider->setMany([
            'mail.from' => 'admin@example.com',
            'orders.max_items' => '250',
            'mail.enabled' => false,
        ]);

        Assert::same($provider->get('mail.from'), 'admin@example.com');
        Assert::same($provider->get('orders.max_items'), 250);
        Assert::false($provider->get('mail.enabled'));
    }

    public function setManyRollsBackOnUnknownKey(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'original@example.com');

        try {
            $provider->setMany([
                'mail.from' => 'changed@example.com',
                'unknown.key' => 'value',
            ]);
        } catch (UnknownSettingException) {
            // Expected
        }

        Assert::same($provider->get('mail.from'), 'original@example.com');
    }

    public function setManyRollsBackOnReadonlyKey(): void
    {
        $provider = new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'app.name' => new SettingDefinition(key: 'app.name', type: SettingType::String, default: 'MyApp'),
                'app.version' => new SettingDefinition(key: 'app.version', type: SettingType::String, default: '1.0', readonly: true),
            ],
        );
        $provider->set('app.name', 'original');

        try {
            $provider->setMany([
                'app.name' => 'changed',
                'app.version' => '2.0',
            ]);
        } catch (ReadonlySettingException) {
            // Expected — readonly check happens before transaction
        }

        Assert::same($provider->get('app.name'), 'original');
    }

    public function setManyWorksWithSecrets(): void
    {
        $provider = $this->providerWithCipher();
        $provider->setMany([
            'billing.stripe_key' => 'sk_live_new123',
            'mail.from' => 'admin@example.com',
        ]);

        Assert::same($provider->get('billing.stripe_key'), 'sk_live_new123');
        Assert::same($provider->get('mail.from'), 'admin@example.com');

        $stored = $this->queryStoredValue('billing.stripe_key');
        Assert::notNull($stored);
        Assert::true(str_starts_with($stored, 'enc:'));
    }

    public function setManyEmptyArrayIsNoOp(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'original@example.com');

        $provider->setMany([]);

        Assert::same($provider->get('mail.from'), 'original@example.com');
    }

    /**
     * @param non-empty-string $table
     */
    private function provider(string $table = 'settings', ?SettingsProvider $fallback = null): DbSettingsProvider
    {
        return new DbSettingsProvider(
            db: $this->db,
            definitions: $this->definitions,
            table: $table,
            fallback: $fallback,
        );
    }

    private function providerWithCipher(?Cipher $cipher = null): DbSettingsProvider
    {
        $defs = $this->definitionsWithSecret();

        if ($cipher === null) {
            $keyRing = new KeyRing(
                keys: ['key-2025' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)],
                activeKeyId: 'key-2025',
            );
            $cipher = new SodiumCipher(keyRing: $keyRing);
        }

        return new DbSettingsProvider(
            db: $this->db,
            definitions: $defs,
            cipher: $cipher,
        );
    }

    private function readonlyProvider(): DbSettingsProvider
    {
        return new DbSettingsProvider(
            db: $this->db,
            definitions: [
                'app.version' => new SettingDefinition(
                    key: 'app.version',
                    type: SettingType::String,
                    default: '1.0',
                    readonly: true,
                ),
            ],
        );
    }

    /**
     * @return array<string, SettingDefinition>
     */
    private function definitionsWithSecret(): array
    {
        $defs = $this->definitions;
        $defs['billing.stripe_key'] = new SettingDefinition(key: 'billing.stripe_key', type: SettingType::String, secret: true);

        return $defs;
    }

    private function queryStoredValue(string $key): ?string
    {
        $row = (new \Yiisoft\Db\Query\Query($this->db))
            ->from('settings')
            ->select(['value'])
            ->where(['key' => $key])
            ->one();

        if (!\is_array($row) || !isset($row['value'])) {
            return null;
        }

        /** @var mixed */
        $value = $row['value'];

        return \is_string($value) ? $value : null;
    }

    private function insertRawRow(string $key, string $value): void
    {
        $this->db->createCommand(sql: 'INSERT INTO settings ("key", "value") VALUES (:key, :value)')
            ->bindValues([
                ':key' => $key,
                ':value' => $value,
            ])
            ->execute();
    }

    private function insertOrReplaceRawRow(string $key, string $value): void
    {
        $this->db->createCommand(sql: 'INSERT OR REPLACE INTO settings ("key", "value") VALUES (:key, :value)')
            ->bindValues([
                ':key' => $key,
                ':value' => $value,
            ])
            ->execute();
    }
}
