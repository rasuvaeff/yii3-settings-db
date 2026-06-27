<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Crypto;

use InvalidArgumentException;
use Rasuvaeff\Yii3Settings\Crypto\UnknownEncryptionKeyException;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(KeyRing::class)]
final class KeyRingTest
{
    private const int KEY_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;

    private function validKey(): string
    {
        return random_bytes(self::KEY_BYTES);
    }

    public function createsWithValidKeys(): void
    {
        $keyId = 'key-2025';
        $keyRing = new KeyRing(
            keys: [$keyId => $this->validKey()],
            activeKeyId: $keyId,
        );

        Assert::same($keyRing->activeKeyId(), $keyId);
        Assert::same(strlen($keyRing->keyFor($keyId)), 32);
    }

    public function multipleKeys(): void
    {
        $keyRing = new KeyRing(
            keys: [
                'key-v1' => $this->validKey(),
                'key-v2' => $this->validKey(),
            ],
            activeKeyId: 'key-v2',
        );

        Assert::same($keyRing->activeKeyId(), 'key-v2');
        Assert::true($keyRing->keyFor('key-v1') !== '');
        Assert::true($keyRing->keyFor('key-v2') !== '');
    }

    public function throwsOnActiveKeyNotFound(): void
    {
        try {
            new KeyRing(
                keys: ['exists' => $this->validKey()],
                activeKeyId: 'missing',
            );
            Assert::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains('Active key ID "missing" not found in key set');
        }
    }

    public function throwsOnKeyTooShort(): void
    {
        try {
            new KeyRing(
                keys: ['k' => 'too short'],
                activeKeyId: 'k',
            );
            Assert::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains(sprintf('must be exactly %d bytes', self::KEY_BYTES));
        }
    }

    public function throwsOnKeyTooLong(): void
    {
        try {
            new KeyRing(
                keys: ['k' => random_bytes(self::KEY_BYTES + 1)],
                activeKeyId: 'k',
            );
            Assert::fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            Assert::string($e->getMessage())->contains(sprintf('must be exactly %d bytes', self::KEY_BYTES));
        }
    }

    public function throwsOnUnknownKeyId(): void
    {
        $keyRing = new KeyRing(
            keys: ['exists' => $this->validKey()],
            activeKeyId: 'exists',
        );

        try {
            $keyRing->keyFor('missing');
            Assert::fail('Expected UnknownEncryptionKeyException');
        } catch (UnknownEncryptionKeyException $e) {
            Assert::string($e->getMessage())->contains('Unknown encryption key ID "missing"');
        }
    }
}
