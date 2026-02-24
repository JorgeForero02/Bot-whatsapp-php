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

    public function getMediaUrl($mediaId)
    {
        try {
            $response = $this->client->get($mediaId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken
                ]
            ]);

            $mediaData = json_decode($response->getBody()->getContents(), true);
            return $mediaData['url'] ?? null;

        } catch (\Exception $e) {
            $this->logger->error('WhatsApp Get Media URL Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function downloadMedia($mediaId)
    {
        try {
            // Get media URL
            $mediaUrl = $this->getMediaUrl($mediaId);

            if (!$mediaUrl) {
                throw new \Exception('Media URL not found');
            }

            // Download media file
            $fileResponse = $this->client->get($mediaUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken
                ]
            ]);

            return $fileResponse->getBody()->getContents();

        } catch (\Exception $e) {
            $this->logger->error('WhatsApp Media Download Error: ' . $e->getMessage());
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
        $messageType = $message['type'] ?? 'text';
        
        $data = [
            'from' => $message['from'] ?? null,
            'text' => '',
            'message_id' => $message['id'] ?? null,
            'timestamp' => $message['timestamp'] ?? time(),
            'contact_name' => $value['contacts'][0]['profile']['name'] ?? 'Unknown',
            'type' => $messageType
        ];

        // Handle different message types
        if ($messageType === 'text') {
            $data['text'] = $message['text']['body'] ?? '';
        } elseif ($messageType === 'audio') {
            $data['audio_id'] = $message['audio']['id'] ?? null;
            $data['mime_type'] = $message['audio']['mime_type'] ?? 'audio/ogg';
        }

        return $data;
    }
}
