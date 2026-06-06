<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Crypto;

use Rasuvaeff\Yii3Settings\Crypto\UnknownEncryptionKeyException;

/**
 * @api
 */
final readonly class KeyRing
{
    private string $activeKeyId;

    /**
     * @var array<non-empty-string, string>
     */
    private array $keys;

    /**
     * @param array<non-empty-string, string> $keys keyId => 32-byte raw key
     * @param non-empty-string $activeKeyId key used for new encryptions
     */
    public function __construct(
        array $keys,
        string $activeKeyId,
    ) {
        if ($keys === []) {
            throw new \InvalidArgumentException('At least one key is required');
        }
        if (!array_key_exists(key: $activeKeyId, array: $keys)) {
            throw new \InvalidArgumentException(
                message: sprintf('Active key ID "%s" not found in key set', $activeKeyId),
            );
        }

        foreach ($keys as $keyId => $key) {
            if (strlen(string: $key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
                throw new \InvalidArgumentException(
                    message: sprintf(
                        'Key "%s" must be exactly %d bytes, got %d',
                        $keyId,
                        SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
                        strlen(string: $key),
                    ),
                );
            }
        }

        $this->keys = $keys;
        $this->activeKeyId = $activeKeyId;
    }

    public function activeKeyId(): string
    {
        return $this->activeKeyId;
    }

    /**
     * @throws UnknownEncryptionKeyException
     */
    public function keyFor(string $keyId): string
    {
        if (!array_key_exists(key: $keyId, array: $this->keys)) {
            throw new UnknownEncryptionKeyException(
                message: sprintf('Unknown encryption key ID "%s"', $keyId),
            );
        }

        return $this->keys[$keyId];
    }
}
