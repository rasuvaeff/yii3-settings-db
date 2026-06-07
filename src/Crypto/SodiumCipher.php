<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3SettingsDb\Crypto;

use Rasuvaeff\Yii3Settings\Crypto\Cipher;
use Rasuvaeff\Yii3Settings\Crypto\DecryptionException;

/**
 * @api
 */
final readonly class SodiumCipher implements Cipher
{
    private const string ENVELOPE_PREFIX = 'enc:';

    public function __construct(
        private KeyRing $keyRing,
    ) {}

    public function activeKeyId(): string
    {
        return $this->keyRing->activeKeyId();
    }

    #[\Override]
    public function encrypt(string $plaintext, string $aad): string
    {
        $keyId = $this->keyRing->activeKeyId();
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $aad,
            $nonce,
            $this->keyRing->keyFor($keyId),
        );

        return sprintf(
            '%sv:%s:%s:%s',
            self::ENVELOPE_PREFIX,
            $keyId,
            base64_encode($nonce),
            base64_encode($ciphertext),
        );
    }

    #[\Override]
    public function decrypt(string $ciphertext, string $aad): string
    {
        if (!str_starts_with($ciphertext, self::ENVELOPE_PREFIX)) {
            throw new DecryptionException('Invalid envelope format');
        }

        $body = substr(string: $ciphertext, offset: strlen(self::ENVELOPE_PREFIX));
        $parts = explode(':', $body, limit: 4);

        if (count($parts) !== 4 || $parts[0] !== 'v') {
            throw new DecryptionException('Invalid envelope format');
        }

        $keyId = $parts[1];
        $nonce = base64_decode($parts[2], strict: true);
        $encrypted = base64_decode($parts[3], strict: true);

        if ($nonce === false || $encrypted === false) {
            throw new DecryptionException('Invalid envelope encoding');
        }

        if (strlen($nonce) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES) {
            throw new DecryptionException('Invalid nonce length in envelope');
        }

        $key = $this->keyRing->keyFor($keyId);
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            $encrypted,
            $aad,
            $nonce,
            $key,
        );

        if ($plaintext === false) {
            throw new DecryptionException(
                message: 'Failed to decrypt: data has been tampered with or AAD mismatch',
            );
        }

        return $plaintext;
    }
}
