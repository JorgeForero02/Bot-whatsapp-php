<?php

namespace App\Services;

class EncryptionService
{
    private $cipherKey;
    private $cipher = 'aes-256-cbc';

    public function __construct($cipherKey = null)
    {
        $this->cipherKey = $cipherKey ?: getenv('APP_CIPHER_KEY');
        
        if (empty($this->cipherKey)) {
            throw new \RuntimeException('APP_CIPHER_KEY is not configured. Set it in your .env file.');
        }
    }

    public function encrypt(string $value): string
    {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt($value, $this->cipher, $this->cipherKey, OPENSSL_RAW_DATA, $iv);
        
        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }
        
        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $value): string
    {
        $data = base64_decode($value);
        
        if ($data === false) {
            throw new \RuntimeException('Invalid encrypted data format');
        }
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->cipherKey, OPENSSL_RAW_DATA, $iv);
        
        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed - invalid key or corrupted data');
        }
        
        return $decrypted;
    }

    public function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        return strlen($decoded) > $ivLength;
    }
}
