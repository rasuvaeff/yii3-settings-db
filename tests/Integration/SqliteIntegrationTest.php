<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3Settings\CachedSettingsProvider;
use Rasuvaeff\Yii3Settings\ConfigSettingsProvider;
use Rasuvaeff\Yii3Settings\Crypto\Cipher;
use Rasuvaeff\Yii3Settings\Exception\UnknownSettingException;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingsInspector;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\SettingType;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;
use Rasuvaeff\Yii3SettingsDb\Crypto\SodiumCipher;
use Rasuvaeff\Yii3SettingsDb\DbSettingsProvider;
use Rasuvaeff\Yii3SettingsDb\Exception\InvalidSettingRowException;
use Rasuvaeff\Yii3SettingsDb\Tests\ArrayCache;
use Rasuvaeff\Yii3SettingsDb\Tests\NullPsr16Cache;
use Yiisoft\Db\Cache\SchemaCache;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection as SqliteConnection;
use Yiisoft\Db\Sqlite\Driver as SqliteDriver;

#[CoversClass(DbSettingsProvider::class)]
final class SqliteIntegrationTest extends TestCase
{
    private ConnectionInterface $db;

    /**
     * @var array<string, SettingDefinition>
     */
    private array $definitions;

    #[\Override]
    protected function setUp(): void
    {
        $driver = new SqliteDriver(dsn: 'sqlite::memory:');
        $schemaCache = new SchemaCache(psrCache: new NullPsr16Cache());
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

    #[\Override]
    protected function tearDown(): void
    {
        $this->db->close();
    }

    #[Test]
    public function hasReturnsTrueForDefinedKeyWithoutStoredRow(): void
    {
        $provider = $this->provider();

        $this->assertTrue($provider->has('mail.from'));
    }

    #[Test]
    public function hasReturnsFalseForUnknownKey(): void
    {
        $provider = $this->provider();

        $this->assertFalse($provider->has('unknown'));
    }

    #[Test]
    public function getReturnsDefaultWhenNoStoredRowExists(): void
    {
        $provider = $this->provider();

        $this->assertSame('noreply@example.com', $provider->get('mail.from'));
    }

    #[Test]
    public function setAndGetStringValue(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');

        $this->assertSame('admin@example.com', $provider->get('mail.from'));
    }

    #[Test]
    public function setAndGetIntValue(): void
    {
        $provider = $this->provider();
        $provider->set('orders.max_items', '250');

        $this->assertSame(250, $provider->get('orders.max_items'));
    }

    #[Test]
    public function setAndGetBoolValue(): void
    {
        $provider = $this->provider();
        $provider->set('mail.enabled', false);

        $this->assertFalse($provider->get('mail.enabled'));
    }

    #[Test]
    public function setAndGetFloatValue(): void
    {
        $provider = $this->provider();
        $provider->set('vat.rate', '21.5');

        $this->assertSame(21.5, $provider->get('vat.rate'));
    }

    #[Test]
    public function setAndGetArrayValue(): void
    {
        $provider = $this->provider();
        $provider->set('app.features', ['search' => true, 'beta' => ['a', 'b']]);

        $this->assertSame(['search' => true, 'beta' => ['a', 'b']], $provider->get('app.features'));
    }


    #[Test]
    public function getReturnsFallbackValueWhenNoStoredRow(): void
    {
        $provider = $this->provider(fallback: new ConfigSettingsProvider(
            definitions: $this->definitions,
            values: ['mail.from' => 'config@example.com'],
        ));

        $this->assertSame('config@example.com', $provider->get('mail.from'));
    }

    #[Test]
    public function getFallsBackToDefinitionDefaultWhenFallbackHasNoValue(): void
    {
        $provider = $this->provider(fallback: new ConfigSettingsProvider(
            definitions: $this->definitions,
            values: [],
        ));

        $this->assertSame('noreply@example.com', $provider->get('mail.from'));
    }

    #[Test]
    public function storedRowTakesPrecedenceOverFallback(): void
    {
        $provider = $this->provider(fallback: new ConfigSettingsProvider(
            definitions: $this->definitions,
            values: ['mail.from' => 'config@example.com'],
        ));
        $provider->set('mail.from', 'db@example.com');

        $this->assertSame('db@example.com', $provider->get('mail.from'));
    }

    #[Test]
    public function getReadsRequestedKeyWhenAnotherRowExists(): void
    {
        $this->insertRawRow(key: 'mail.from', value: 'admin@example.com');
        $this->insertRawRow(key: 'orders.max_items', value: '250');
        $provider = $this->provider();

        $this->assertSame('admin@example.com', $provider->get('mail.from'));
        $this->assertSame(250, $provider->get('orders.max_items'));
    }

    #[Test]
    public function removeDeletesStoredValueAndFallsBackToDefault(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');
        $provider->remove('mail.from');

        $this->assertSame('noreply@example.com', $provider->get('mail.from'));
    }


    #[Test]
    public function removeDeletesOnlyRequestedRow(): void
    {
        $provider = $this->provider();
        $provider->set('mail.from', 'admin@example.com');
        $provider->set('orders.max_items', 250);

        $provider->remove('mail.from');

        $this->assertSame('noreply@example.com', $provider->get('mail.from'));
        $this->assertSame(250, $provider->get('orders.max_items'));
    }

    #[Test]
    public function removeMissingStoredRowIsNoOp(): void
    {
        $provider = $this->provider();
        $provider->remove('mail.from');

        $this->assertSame('noreply@example.com', $provider->get('mail.from'));
    }

    #[Test]
    public function setOverwritesExistingValue(): void
    {
        $provider = $this->provider();
        $provider->set('orders.max_items', 100);
        $provider->set('orders.max_items', 200);

        $this->assertSame(200, $provider->get('orders.max_items'));
    }

    #[Test]
    public function throwsOnUnknownKeyGet(): void
    {
        $provider = $this->provider();

        $this->expectException(UnknownSettingException::class);
        $this->expectExceptionMessage('Unknown setting "unknown"');

        $provider->get('unknown');
    }

    #[Test]
    public function throwsOnUnknownKeySet(): void
    {
        $provider = $this->provider();

        $this->expectException(UnknownSettingException::class);
        $this->expectExceptionMessage('Unknown setting "unknown"');

        $provider->set('unknown', 'value');
    }

    #[Test]
    public function throwsOnUnknownKeyRemove(): void
    {
        $provider = $this->provider();

        $this->expectException(UnknownSettingException::class);
        $this->expectExceptionMessage('Unknown setting "unknown"');

        $provider->remove('unknown');
    }

    #[Test]
    public function throwsOnInvalidStoredInt(): void
    {
        $this->insertRawRow(key: 'orders.max_items', value: 'abc');
        $provider = $this->provider();

        $this->expectException(InvalidSettingRowException::class);
        $this->expectExceptionMessage('Invalid stored int for setting "orders.max_items"');

        $provider->get('orders.max_items');
    }

    #[Test]
    public function throwsOnInvalidStoredJson(): void
    {
        $this->insertRawRow(key: 'app.features', value: 'not-json');
        $provider = $this->provider();

        $this->expectException(InvalidSettingRowException::class);
        $this->expectExceptionMessage('Invalid stored JSON for setting "app.features"');

        $provider->get('app.features');
    }

    #[Test]
    public function integratesWithCoreCachedSettingsProvider(): void
    {
        $this->insertRawRow(key: 'mail.from', value: 'first@example.com');
        $provider = $this->provider();
        $cache = new ArrayCache();
        $cached = new CachedSettingsProvider(
            inner: $provider,
            cache: $cache,
            definitions: $this->definitions,
            ttl: 60,
        );

        $this->assertSame('first@example.com', $cached->get('mail.from'));
        $this->assertSame(1, $cache->getCalls);
        $this->assertSame(1, $cache->setCalls);

        $this->insertOrReplaceRawRow(key: 'mail.from', value: 'second@example.com');

        $this->assertSame('first@example.com', $cached->get('mail.from'));

        $cached->clear('mail.from');

        $this->assertSame('second@example.com', $cached->get('mail.from'));
    }

    #[Test]
    public function constructorThrowsWhenSecretDefWithoutCipher(): void
    {
        $defs = $this->definitionsWithSecret();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cipher is required when secret definitions exist');

        new DbSettingsProvider(db: $this->db, definitions: $defs);
    }

    #[Test]
    public function secretSetAndGetRoundtrip(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_live_test123');

        $this->assertSame('sk_live_test123', $provider->get('billing.stripe_key'));
    }

    #[Test]
    public function secretValueIsEncryptedAtRest(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_live_test123');

        $stored = $this->queryStoredValue('billing.stripe_key');
        $this->assertNotNull($stored);
        $this->assertStringStartsWith('enc:', $stored);
        $this->assertStringNotContainsString('sk_live_test123', $stored);
    }

    #[Test]
    public function secretGetReturnsDefaultWhenNoStoredRow(): void
    {
        $provider = $this->providerWithCipher();

        $this->assertNull($provider->get('billing.stripe_key'));
    }

    #[Test]
    public function nonSecretKeysUnaffectedByCipher(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('mail.from', 'admin@example.com');

        $stored = $this->queryStoredValue('mail.from');
        $this->assertNotNull($stored);
        $this->assertStringNotContainsString('enc:', $stored);
        $this->assertSame('admin@example.com', $provider->get('mail.from'));
    }

    #[Test]
    public function describeForDbOverride(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('mail.from', 'admin@example.com');

        $state = $provider->describe(key: 'mail.from');

        $this->assertSame('mail.from', $state->key);
        $this->assertSame('admin@example.com', $state->effectiveValue);
        $this->assertTrue($state->hasStoredOverride);
        $this->assertSame('db', $state->source);
        $this->assertFalse($state->isSecret);
        $this->assertTrue($state->isWritable);
    }

    #[Test]
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

        $this->assertSame('config', $state->source);
        $this->assertSame('config@example.com', $state->effectiveValue);
        $this->assertFalse($state->hasStoredOverride);
    }

    #[Test]
    public function describeForDefaultSource(): void
    {
        $provider = $this->providerWithCipher();

        $state = $provider->describe(key: 'mail.from');

        $this->assertSame('default', $state->source);
        $this->assertSame('noreply@example.com', $state->effectiveValue);
        $this->assertFalse($state->hasStoredOverride);
    }

    #[Test]
    public function describeMasksSecretValue(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_live_test123');

        $state = $provider->describe(key: 'billing.stripe_key');

        $this->assertTrue($state->isSecret);
        $this->assertTrue($state->hasStoredOverride);
        $this->assertSame('db', $state->source);
        $this->assertNull($state->effectiveValue);
    }

    #[Test]
    public function describeSecretWithoutOverride(): void
    {
        $provider = $this->providerWithCipher();

        $state = $provider->describe(key: 'billing.stripe_key');

        $this->assertTrue($state->isSecret);
        $this->assertFalse($state->hasStoredOverride);
        $this->assertNull($state->effectiveValue);
    }

    #[Test]
    public function reencryptSecretsEncryptsPlaintextValue(): void
    {
        $this->insertRawRow(key: 'billing.stripe_key', value: 'sk_old_plaintext');

        $provider = $this->providerWithCipher();
        $count = $provider->reencryptSecrets();

        $this->assertSame(1, $count);

        $stored = $this->queryStoredValue('billing.stripe_key');
        $this->assertNotNull($stored);
        $this->assertStringStartsWith('enc:', $stored);

        $this->assertSame('sk_old_plaintext', $provider->get('billing.stripe_key'));
    }

    #[Test]
    public function reencryptSecretsSkipsNonSecretKeys(): void
    {
        $this->insertRawRow(key: 'mail.from', value: 'admin@example.com');
        $this->insertRawRow(key: 'orders.max_items', value: '250');

        $provider = $this->providerWithCipher();
        $count = $provider->reencryptSecrets();

        $this->assertSame(0, $count);
    }

    #[Test]
    public function reencryptSecretsSkipsAlreadyEncrypted(): void
    {
        $provider = $this->providerWithCipher();
        $provider->set('billing.stripe_key', 'sk_val');

        $count = $provider->reencryptSecrets();

        $this->assertSame(0, $count);
        $this->assertSame('sk_val', $provider->get('billing.stripe_key'));
    }

    #[Test]
    public function dbSettingsProviderImplementsInspector(): void
    {
        $provider = $this->providerWithCipher();

        $this->assertInstanceOf(SettingsInspector::class, $provider);
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
