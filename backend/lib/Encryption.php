<?php
class Encryption
{
    private string $key;

    public function __construct(string $key)
    {
        $decoded = base64_decode($key, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('APP_ENCRYPTION_KEY must be a 32-byte base64 string');
        }
        $this->key = $decoded;
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt secret');
        }

        return json_encode([
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ]);
    }

    public function decrypt(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (!$decoded || !isset($decoded['iv'], $decoded['tag'], $decoded['ciphertext'])) {
            throw new RuntimeException('Invalid encrypted payload');
        }

        $plaintext = openssl_decrypt(
            base64_decode($decoded['ciphertext'], true),
            'aes-256-gcm',
            $this->key,
            OPENSSL_RAW_DATA,
            base64_decode($decoded['iv'], true),
            base64_decode($decoded['tag'], true)
        );

        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt secret');
        }

        return $plaintext;
    }
}
