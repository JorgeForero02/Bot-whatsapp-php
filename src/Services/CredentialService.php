<?php

namespace App\Services;

use App\Core\Database;

class CredentialService
{
    private $db;
    private $encryption;

    public function __construct(Database $db, EncryptionService $encryption)
    {
        $this->db = $db;
        $this->encryption = $encryption;
    }

    public function getWhatsAppCredentials(): array
    {
        $row = $this->db->fetchOne("SELECT * FROM bot_credentials WHERE id = 1");
        
        if (!$row) {
            return [
                'phone_number_id' => '',
                'access_token' => '',
                'app_secret' => '',
                'verify_token' => '',
            ];
        }

        return [
            'phone_number_id' => $row['whatsapp_phone_number_id'] ?? '',
            'access_token' => $this->decryptSafe($row['whatsapp_access_token']),
            'app_secret' => $this->decryptSafe($row['whatsapp_app_secret']),
            'verify_token' => $row['whatsapp_verify_token'] ?? '',
        ];
    }

    public function getOpenAICredentials(): array
    {
        $row = $this->db->fetchOne("SELECT * FROM bot_credentials WHERE id = 1");
        
        if (!$row) {
            return [
                'api_key' => '',
                'model' => 'gpt-3.5-turbo',
                'embedding_model' => 'text-embedding-ada-002',
            ];
        }

        return [
            'api_key' => $this->decryptSafe($row['openai_api_key']),
            'model' => $row['openai_model'] ?? 'gpt-3.5-turbo',
            'embedding_model' => $row['openai_embedding_model'] ?? 'text-embedding-ada-002',
        ];
    }

    public function getGoogleOAuthCredentials(): array
    {
        $row = $this->db->fetchOne("SELECT * FROM google_oauth_credentials WHERE id = 1");
        
        if (!$row) {
            return [
                'client_id' => '',
                'client_secret' => '',
                'access_token' => '',
                'refresh_token' => '',
                'token_expires_at' => null,
                'calendar_id' => '',
            ];
        }

        return [
            'client_id' => $row['client_id'] ?? '',
            'client_secret' => $this->decryptSafe($row['client_secret']),
            'access_token' => $this->decryptSafe($row['access_token']),
            'refresh_token' => $this->decryptSafe($row['refresh_token']),
            'token_expires_at' => $row['token_expires_at'] ?? null,
            'calendar_id' => $row['calendar_id'] ?? '',
        ];
    }

    public function saveWhatsAppCredentials(array $data): void
    {
        $update = [];
        $params = [];

        if (isset($data['phone_number_id'])) {
            $update[] = 'whatsapp_phone_number_id = :phone_number_id';
            $params[':phone_number_id'] = $data['phone_number_id'];
        }
        if (isset($data['access_token']) && $data['access_token'] !== '') {
            $update[] = 'whatsapp_access_token = :access_token';
            $params[':access_token'] = $this->encryption->encrypt($data['access_token']);
        }
        if (isset($data['app_secret']) && $data['app_secret'] !== '') {
            $update[] = 'whatsapp_app_secret = :app_secret';
            $params[':app_secret'] = $this->encryption->encrypt($data['app_secret']);
        }
        if (isset($data['verify_token'])) {
            $update[] = 'whatsapp_verify_token = :verify_token';
            $params[':verify_token'] = $data['verify_token'];
        }

        if (!empty($update)) {
            $sql = "INSERT INTO bot_credentials (id) VALUES (1) ON DUPLICATE KEY UPDATE " . implode(', ', $update);
            $this->db->query($sql, $params);
        }
    }

    public function saveOpenAICredentials(array $data): void
    {
        $update = [];
        $params = [];

        if (isset($data['api_key']) && $data['api_key'] !== '') {
            $update[] = 'openai_api_key = :api_key';
            $params[':api_key'] = $this->encryption->encrypt($data['api_key']);
        }
        if (isset($data['model'])) {
            $update[] = 'openai_model = :model';
            $params[':model'] = $data['model'];
        }
        if (isset($data['embedding_model'])) {
            $update[] = 'openai_embedding_model = :embedding_model';
            $params[':embedding_model'] = $data['embedding_model'];
        }
        if (!empty($update)) {
            $sql = "INSERT INTO bot_credentials (id) VALUES (1) ON DUPLICATE KEY UPDATE " . implode(', ', $update);
            $this->db->query($sql, $params);
        }
    }

    public function saveGoogleOAuthCredentials(array $data): void
    {
        $update = [];
        $params = [];

        if (isset($data['client_id'])) {
            $update[] = 'client_id = :client_id';
            $params[':client_id'] = $data['client_id'];
        }
        if (isset($data['client_secret']) && $data['client_secret'] !== '') {
            $update[] = 'client_secret = :client_secret';
            $params[':client_secret'] = $this->encryption->encrypt($data['client_secret']);
        }
        if (isset($data['access_token']) && $data['access_token'] !== '') {
            $update[] = 'access_token = :access_token';
            $params[':access_token'] = $this->encryption->encrypt($data['access_token']);
        }
        if (isset($data['refresh_token']) && $data['refresh_token'] !== '') {
            $update[] = 'refresh_token = :refresh_token';
            $params[':refresh_token'] = $this->encryption->encrypt($data['refresh_token']);
        }
        if (isset($data['token_expires_at'])) {
            $update[] = 'token_expires_at = :token_expires_at';
            $params[':token_expires_at'] = $data['token_expires_at'];
        }
        if (isset($data['calendar_id'])) {
            $update[] = 'calendar_id = :calendar_id';
            $params[':calendar_id'] = $data['calendar_id'];
        }

        if (!empty($update)) {
            $sql = "INSERT INTO google_oauth_credentials (id) VALUES (1) ON DUPLICATE KEY UPDATE " . implode(', ', $update);
            $this->db->query($sql, $params);
        }
    }

    public function getSetting(string $key, $default = null)
    {
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = :key",
            [':key' => $key]
        );
        return $row ? $row['setting_value'] : $default;
    }

    public function hasWhatsAppCredentials(): bool
    {
        $creds = $this->getWhatsAppCredentials();
        return !empty($creds['access_token']) && !empty($creds['phone_number_id']);
    }

    public function hasOpenAICredentials(): bool
    {
        $creds = $this->getOpenAICredentials();
        return !empty($creds['api_key']);
    }

    public function hasGoogleOAuthCredentials(): bool
    {
        $creds = $this->getGoogleOAuthCredentials();
        return !empty($creds['access_token']) && !empty($creds['calendar_id']);
    }

    private function decryptSafe($value): string
    {
        if (empty($value)) {
            return '';
        }
        try {
            return $this->encryption->decrypt($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
