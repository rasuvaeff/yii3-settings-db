<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Crypto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Rasuvaeff\Yii3Settings\Crypto\DecryptionException;
use Rasuvaeff\Yii3Settings\Crypto\UnknownEncryptionKeyException;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;
use Rasuvaeff\Yii3SettingsDb\Crypto\SodiumCipher;

#[CoversClass(SodiumCipher::class)]
final class SodiumCipherTest extends TestCase
{
    private KeyRing $keyRing;

    private SodiumCipher $cipher;

    #[\Override]
    protected function setUp(): void
    {
        $this->keyRing = new KeyRing(
            keys: ['key-2025' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)],
            activeKeyId: 'key-2025',
        );
        $this->cipher = new SodiumCipher(keyRing: $this->keyRing);
    }

    #[Test]
    public function roundtripEncryptDecrypt(): void
    {
        $plaintext = 'sk_live_secret_key_123';
        $aad = 'billing.stripe_key';

        $ciphertext = $this->cipher->encrypt(plaintext: $plaintext, aad: $aad);
        $this->assertStringStartsWith('enc:', $ciphertext);

        $decrypted = $this->cipher->decrypt(ciphertext: $ciphertext, aad: $aad);
        $this->assertSame($plaintext, $decrypted);
    }

    #[Test]
    public function tamperedCiphertextThrows(): void
    {
        $ciphertext = $this->cipher->encrypt(plaintext: 'secret', aad: 'my.key');
        $parts = explode(':', $ciphertext);

        $parts[3] = base64_encode(random_bytes(32));

        $tampered = implode(':', $parts);

        $this->expectException(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: $tampered, aad: 'my.key');
    }

    #[Test]
    public function wrongAadThrows(): void
    {
        $ciphertext = $this->cipher->encrypt(plaintext: 'secret', aad: 'my.key');

        $this->expectException(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: $ciphertext, aad: 'other.key');
    }

    #[Test]
    public function invalidEnvelopeFormatThrows(): void
    {
        $this->expectException(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: 'not-a-valid-envelope', aad: 'key');
    }

    #[Test]
    public function invalidBase64Throws(): void
    {
        $this->expectException(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: 'enc:v:key-1:!!!bad!!!:!!!bad!!!', aad: 'key');
    }

    #[Test]
    public function wrongEnvelopePrefixThrows(): void
    {
        $this->expectException(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: 'not-enc:v:key-1:YQ==:YQ==', aad: 'key');
    }

    #[Test]
    public function keyRotationNewKeyEncryptsOldKeyDecrypts(): void
    {
        $keyRing = new KeyRing(
            keys: [
                'key-v1' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES),
            ],
            activeKeyId: 'key-v1',
        );
        $oldCipher = new SodiumCipher(keyRing: $keyRing);
        $ciphertext = $oldCipher->encrypt(plaintext: 'secret', aad: 'my.key');

        $newKeyRing = new KeyRing(
            keys: [
                'key-v1' => $keyRing->keyFor('key-v1'),
                'key-v2' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES),
            ],
            activeKeyId: 'key-v2',
        );
        $newCipher = new SodiumCipher(keyRing: $newKeyRing);

        $decrypted = $newCipher->decrypt(ciphertext: $ciphertext, aad: 'my.key');
        $this->assertSame('secret', $decrypted);
    }

    #[Test]
    public function unknownKeyIdThrows(): void
    {
        $ciphertext = $this->cipher->encrypt(plaintext: 'secret', aad: 'my.key');

        $emptyKeyRing = new KeyRing(
            keys: ['key-other' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)],
            activeKeyId: 'key-other',
        );
        $emptyCipher = new SodiumCipher(keyRing: $emptyKeyRing);

        $this->expectException(UnknownEncryptionKeyException::class);
        $emptyCipher->decrypt(ciphertext: $ciphertext, aad: 'my.key');
    }
}
