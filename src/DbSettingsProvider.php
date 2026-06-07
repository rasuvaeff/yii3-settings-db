<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb;

use Rasuvaeff\Yii3Settings\ConfigSettingsProvider;
use Rasuvaeff\Yii3Settings\Crypto\Cipher;
use Rasuvaeff\Yii3Settings\Exception\UnknownSettingException;
use Rasuvaeff\Yii3Settings\SettingDefinition;
use Rasuvaeff\Yii3Settings\SettingsInspector;
use Rasuvaeff\Yii3Settings\SettingsProvider;
use Rasuvaeff\Yii3Settings\SettingState;
use Rasuvaeff\Yii3Settings\WritableSettingsProvider;
use Rasuvaeff\Yii3SettingsDb\Crypto\SodiumCipher;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Query\Query;

/**
 * @api
 */
final readonly class DbSettingsProvider implements WritableSettingsProvider, SettingsInspector
{
    /**
     * @var array<string, SettingDefinition>
     */
    private array $definitions;

    private SettingRowMapper $rowMapper;

    /**
     * @param array<string, SettingDefinition|array{type: string, default?: mixed, secret?: bool}> $definitions
     * @param non-empty-string $table
     * @param SettingsProvider|null $fallback source for keys without a stored DB row
     *                                         (e.g. config `values`); must recognize the same keys
     * @param Cipher|null $cipher optional encryption for secret settings
     */
    public function __construct(
        private ConnectionInterface $db,
        array $definitions = [],
        private string $table = 'settings',
        private ?SettingsProvider $fallback = null,
        private ?Cipher $cipher = null,
    ) {
        $this->definitions = ConfigSettingsProvider::normalizeDefinitions($definitions);
        $this->rowMapper = new SettingRowMapper();

        foreach ($this->definitions as $definition) {
            if ($definition->isSecret() && $cipher === null) {
                throw new \InvalidArgumentException(
                    message: sprintf('Cipher is required when secret definitions exist (setting "%s")', $definition->key),
                );
            }
        }
    }

    #[\Override]
    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    #[\Override]
    public function get(string $key): mixed
    {
        $definition = $this->definition($key);
        $row = $this->queryRow($key);

        if ($row === null) {
            if ($this->fallback === null) {
                return $definition->default;
            }

            return $this->fallback->get($key);
        }

        if ($definition->isSecret()) {
            assert($this->cipher !== null);

            $storedValue = $row['value'];
            if (!\is_string($storedValue)) {
                throw new \InvalidArgumentException(
                    message: sprintf('Malformed stored value for secret setting "%s"', $key),
                );
            }

            $plaintext = $this->cipher->decrypt(ciphertext: $storedValue, aad: $key);

            return $this->rowMapper->toValue(row: ['value' => $plaintext], definition: $definition);
        }

        return $this->rowMapper->toValue(row: $row, definition: $definition);
    }

    #[\Override]
    public function set(string $key, mixed $value): void
    {
        $definition = $this->definition($key);

        if ($definition->isSecret()) {
            assert($this->cipher !== null);

            $plaintext = (string) $definition->cast($value);
            $encrypted = $this->cipher->encrypt(plaintext: $plaintext, aad: $key);

            $this->db->createCommand()
                ->upsert($this->table, ['key' => $key, 'value' => $encrypted])
                ->execute();

            return;
        }

        $this->db->createCommand()
            ->upsert(
                $this->table,
                [
                    'key' => $key,
                    'value' => $this->rowMapper->toStorage(definition: $definition, value: $value),
                ],
            )
            ->execute();
    }

    #[\Override]
    public function remove(string $key): void
    {
        $this->definition($key);

        $this->db->createCommand()
            ->delete($this->table, ['key' => $key])
            ->execute();
    }

    #[\Override]
    public function describe(string $key): SettingState
    {
        $definition = $this->definition($key);
        $row = $this->queryRow($key);
        $hasStoredOverride = $row !== null;

        $source = 'default';
        if ($hasStoredOverride) {
            $source = 'db';
        } elseif ($this->fallback !== null && $this->fallback->has($key)) {
            $source = 'config';
        }

        $effectiveValue = null;
        if (!$definition->isSecret()) {
            /** @var mixed */
            $effectiveValue = $hasStoredOverride
                ? $this->rowMapper->toValue(row: $row, definition: $definition)
                : $this->get($key);
        }

        return new SettingState(
            key: $key,
            effectiveValue: $effectiveValue,
            hasStoredOverride: $hasStoredOverride,
            source: $source,
            isSecret: $definition->isSecret(),
            isWritable: true,
        );
    }

    /**
     * Re-encrypts all stored secret values using the active key.
     * Detects plaintext values (without envelope prefix) and encrypts them.
     *
     * @return int number of re-encrypted keys
     */
    public function reencryptSecrets(): int
    {
        assert($this->cipher !== null);

        $rows = (new Query($this->db))
            ->from($this->table)
            ->select(['key', 'value'])
            ->all();

        $count = 0;

        foreach ($rows as $row) {
            /** @var array<array-key, mixed> $row */
            $key = $row['key'];
            if (!\is_string($key) || !isset($this->definitions[$key])) {
                continue;
            }

            $definition = $this->definitions[$key];
            if (!$definition->isSecret()) {
                continue;
            }

            $storedValue = $row['value'];
            if (!\is_string($storedValue)) {
                continue;
            }

            if (!str_starts_with($storedValue, 'enc:')) {
                $plaintext = $storedValue;
            } else {
                if ($this->cipher instanceof SodiumCipher) {
                    $keyId = $this->extractKeyIdFromEnvelope($storedValue);
                    if ($keyId !== null && $this->cipher->activeKeyId() === $keyId) {
                        continue;
                    }
                }

                try {
                    $plaintext = $this->cipher->decrypt(ciphertext: $storedValue, aad: $key);
                } catch (\Throwable) {
                    continue;
                }
            }

            $reEncrypted = $this->cipher->encrypt(plaintext: $plaintext, aad: $key);
            if ($reEncrypted !== $storedValue) {
                $this->db->createCommand()
                    ->update($this->table, ['value' => $reEncrypted], ['key' => $key])
                    ->execute();

                $count++;
            }
        }

        return $count;
    }

    private function definition(string $key): SettingDefinition
    {
        if (!isset($this->definitions[$key])) {
            throw new UnknownSettingException(
                message: sprintf('Unknown setting "%s"', $key),
            );
        }

        return $this->definitions[$key];
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function queryRow(string $key): ?array
    {
        $row = (new Query($this->db))
            ->from($this->table)
            ->where(['key' => $key])
            ->one();

        return \is_array($row) ? $row : null;
    }

    private function extractKeyIdFromEnvelope(string $envelope): ?string
    {
        $body = substr(string: $envelope, offset: 6);

        $separatorPos = strpos($body, ':');

        if ($separatorPos === false) {
            return null;
        }

        $keyId = substr(string: $body, offset: 0, length: $separatorPos);

        return $keyId === '' ? null : $keyId;
    }
}
