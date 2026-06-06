<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Crypto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3Settings\Crypto\UnknownEncryptionKeyException;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;

#[CoversClass(KeyRing::class)]
final class KeyRingTest extends TestCase
{
    private const int KEY_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES;

    private function validKey(): string
    {
        return random_bytes(self::KEY_BYTES);
    }

    #[Test]
    public function createsWithValidKeys(): void
    {
        $keyId = 'key-2025';
        $keyRing = new KeyRing(
            keys: [$keyId => $this->validKey()],
            activeKeyId: $keyId,
        );

        $this->assertSame($keyId, $keyRing->activeKeyId());
        $this->assertSame(32, strlen($keyRing->keyFor($keyId)));
    }

    #[Test]
    public function multipleKeys(): void
    {
        $keyRing = new KeyRing(
            keys: [
                'key-v1' => $this->validKey(),
                'key-v2' => $this->validKey(),
            ],
            activeKeyId: 'key-v2',
        );

        $this->assertSame('key-v2', $keyRing->activeKeyId());
        $this->assertNotEmpty($keyRing->keyFor('key-v1'));
        $this->assertNotEmpty($keyRing->keyFor('key-v2'));
    }

    #[Test]
    public function throwsOnActiveKeyNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Active key ID "missing" not found in key set');

        new KeyRing(
            keys: ['exists' => $this->validKey()],
            activeKeyId: 'missing',
        );
    }

    #[Test]
    public function throwsOnKeyTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('must be exactly %d bytes', self::KEY_BYTES));

        new KeyRing(
            keys: ['k' => 'too short'],
            activeKeyId: 'k',
        );
    }

    #[Test]
    public function throwsOnKeyTooLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('must be exactly %d bytes', self::KEY_BYTES));

        new KeyRing(
            keys: ['k' => random_bytes(self::KEY_BYTES + 1)],
            activeKeyId: 'k',
        );
    }

    #[Test]
    public function throwsOnUnknownKeyId(): void
    {
        $this->expectException(UnknownEncryptionKeyException::class);
        $this->expectExceptionMessage('Unknown encryption key ID "missing"');

        $keyRing = new KeyRing(
            keys: ['exists' => $this->validKey()],
            activeKeyId: 'exists',
        );

        $keyRing->keyFor('missing');
    }
}
