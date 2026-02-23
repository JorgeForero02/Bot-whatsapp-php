<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Core\Logger;

class WhatsAppService
{
    private $client;
    private $accessToken;
    private $phoneNumberId;
    private $apiVersion;
    private $logger;

    public function __construct($accessToken, $phoneNumberId, $apiVersion, Logger $logger)
    {
        $this->accessToken = $accessToken;
        $this->phoneNumberId = $phoneNumberId;
        $this->apiVersion = $apiVersion;
        $this->logger = $logger;

        $this->client = new Client([
            'base_uri' => 'https://graph.facebook.com/' . $this->apiVersion . '/',
            'timeout' => 30,
            'verify' => false
        ]);
    }

    public function sendMessage($to, $message)
    {
        try {
            $response = $this->client->post($this->phoneNumberId . '/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message
                    ]
                ]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('WhatsApp: Message sent', [
                'to' => $to,
                'message_id' => $data['messages'][0]['id'] ?? null
            ]);

            return $data['messages'][0]['id'] ?? null;

        } catch (\Exception $e) {
            $this->logger->error('WhatsApp Send Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function markAsRead($messageId)
    {
        try {
            $this->client->post($this->phoneNumberId . '/messages', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId
                ]
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('WhatsApp Mark Read Error: ' . $e->getMessage());
            return false;
        }
    }

    public function verifyWebhook($mode, $token, $challenge, $verifyToken)
    {
        if ($mode === 'subscribe' && $token === $verifyToken) {
            return $challenge;
        }
        return false;
    }

    public function parseWebhookPayload($payload)
    {
        if (!isset($payload['entry'][0]['changes'][0]['value'])) {
            return null;
        }

        $value = $payload['entry'][0]['changes'][0]['value'];

        if (!isset($value['messages'][0])) {
            return null;
        }

        $message = $value['messages'][0];
        $contact = $value['contacts'][0] ?? [];

        return [
            'message_id' => $message['id'] ?? null,
            'from' => $message['from'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
            'type' => $message['type'] ?? 'text',
            'text' => $message['text']['body'] ?? '',
            'contact_name' => $contact['profile']['name'] ?? 'Unknown'
        ];
    }
}
