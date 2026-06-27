<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Tests\Crypto;

use Rasuvaeff\Yii3Settings\Crypto\DecryptionException;
use Rasuvaeff\Yii3Settings\Crypto\UnknownEncryptionKeyException;
use Rasuvaeff\Yii3SettingsDb\Crypto\KeyRing;
use Rasuvaeff\Yii3SettingsDb\Crypto\SodiumCipher;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
#[Covers(SodiumCipher::class)]
final class SodiumCipherTest
{
    private KeyRing $keyRing;

    private SodiumCipher $cipher;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->keyRing = new KeyRing(
            keys: ['key-2025' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)],
            activeKeyId: 'key-2025',
        );
        $this->cipher = new SodiumCipher(keyRing: $this->keyRing);
    }

    public function roundtripEncryptDecrypt(): void
    {
        $plaintext = 'sk_live_secret_key_123';
        $aad = 'billing.stripe_key';

        $ciphertext = $this->cipher->encrypt(plaintext: $plaintext, aad: $aad);
        Assert::true(str_starts_with($ciphertext, 'enc:'));

        $decrypted = $this->cipher->decrypt(ciphertext: $ciphertext, aad: $aad);
        Assert::same($decrypted, $plaintext);
    }

    public function tamperedCiphertextThrows(): void
    {
        $ciphertext = $this->cipher->encrypt(plaintext: 'secret', aad: 'my.key');
        $parts = explode(':', $ciphertext);

        $parts[3] = base64_encode(random_bytes(32));

        $tampered = implode(':', $parts);

        Expect::exception(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: $tampered, aad: 'my.key');
    }

    public function wrongAadThrows(): void
    {
        $ciphertext = $this->cipher->encrypt(plaintext: 'secret', aad: 'my.key');

        Expect::exception(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: $ciphertext, aad: 'other.key');
    }

    public function invalidEnvelopeFormatThrows(): void
    {
        Expect::exception(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: 'not-a-valid-envelope', aad: 'key');
    }

    public function invalidBase64Throws(): void
    {
        Expect::exception(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: 'enc:v:key-1:!!!bad!!!:!!!bad!!!', aad: 'key');
    }

    public function wrongEnvelopePrefixThrows(): void
    {
        Expect::exception(DecryptionException::class);
        $this->cipher->decrypt(ciphertext: 'not-enc:v:key-1:YQ==:YQ==', aad: 'key');
    }

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
        Assert::same($decrypted, 'secret');
    }

    public function unknownKeyIdThrows(): void
    {
        $ciphertext = $this->cipher->encrypt(plaintext: 'secret', aad: 'my.key');

        $emptyKeyRing = new KeyRing(
            keys: ['key-other' => random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)],
            activeKeyId: 'key-other',
        );
        $emptyCipher = new SodiumCipher(keyRing: $emptyKeyRing);

        Expect::exception(UnknownEncryptionKeyException::class);
        $emptyCipher->decrypt(ciphertext: $ciphertext, aad: 'my.key');
    }
}
