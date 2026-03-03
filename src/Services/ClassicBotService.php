<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

class ClassicBotService
{
    private const SESSION_TTL_MINUTES = 30;
    private const MAX_ATTEMPTS = 3;

    private Database $db;
    private Logger $logger;

    public function __construct(Database $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function processMessage(string $userMessage, string $userPhone): array
    {
        $fallback = $this->getFallbackMessage();

        $session = $this->getSession($userPhone);

        if ($session) {
            if ($this->isExpired($session['expires_at'])) {
                $this->clearSession($userPhone);
                $session = null;
            }
        }

        $messageLower = mb_strtolower(trim($userMessage));

        if ($session && $session['current_node_id']) {
            $node = $this->getNode((int)$session['current_node_id']);

            if ($node) {
                $matchedOption = $this->matchOptions((int)$node['id'], $messageLower);

                if ($matchedOption) {
                    return $this->resolveNextNode($matchedOption['next_node_id'], $userPhone, $fallback);
                }

                if ($this->matchKeywords($node['trigger_keywords'], $messageLower)) {
                    return $this->resolveNextNode($node['next_node_id'], $userPhone, $fallback);
                }

                $newAttempts = (int)$session['attempts'] + 1;
                $this->logger->info('ClassicBot: no keyword match', [
                    'phone' => $userPhone,
                    'node' => $node['id'],
                    'attempts' => $newAttempts,
                ]);

                if ($newAttempts >= self::MAX_ATTEMPTS) {
                    $this->clearSession($userPhone);
                    return ['type' => 'fallback', 'response' => $fallback];
                }

                $this->updateAttempts($userPhone, $newAttempts);
                return ['type' => 'fallback', 'response' => $fallback];
            }

            $this->clearSession($userPhone);
        }

        $rootNodes = $this->getRootNodes();

        foreach ($rootNodes as $rootNode) {
            if ($this->matchKeywords($rootNode['trigger_keywords'], $messageLower)) {
                return $this->resolveNextNode((int)$rootNode['id'], $userPhone, $fallback);
            }
        }

        return ['type' => 'fallback', 'response' => $fallback];
    }

    private function resolveNextNode(?int $nodeId, string $userPhone, string $fallback): array
    {
        if (!$nodeId) {
            $this->clearSession($userPhone);
            return ['type' => 'fallback', 'response' => $fallback];
        }

        $node = $this->getNode($nodeId);
        if (!$node) {
            $this->clearSession($userPhone);
            return ['type' => 'fallback', 'response' => $fallback];
        }

        $this->saveSession($userPhone, $nodeId);

        if ($node['requires_calendar']) {
            return ['type' => 'calendar', 'response' => $node['message_text']];
        }

        return ['type' => 'response', 'response' => $node['message_text']];
    }

    private function matchKeywords(string $keywordsJson, string $messageLower): bool
    {
        $keywords = json_decode($keywordsJson, true) ?? [];
        foreach ($keywords as $kw) {
            if (strpos($messageLower, mb_strtolower(trim($kw))) !== false) {
                return true;
            }
        }
        return false;
    }

    private function matchOptions(int $nodeId, string $messageLower): ?array
    {
        $options = $this->db->fetchAll(
            "SELECT * FROM flow_options WHERE node_id = :node_id ORDER BY position_order ASC",
            [':node_id' => $nodeId]
        );

        if (!$options) {
            return null;
        }

        foreach ($options as $option) {
            if ($this->matchKeywords($option['option_keywords'], $messageLower)) {
                return $option;
            }
        }
        return null;
    }

    private function getNode(int $nodeId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM flow_nodes WHERE id = :id AND is_active = 1",
            [':id' => $nodeId]
        ) ?: null;
    }

    private function getRootNodes(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM flow_nodes WHERE is_root = 1 AND is_active = 1 ORDER BY position_order ASC",
            []
        ) ?? [];
    }

    private function getSession(string $userPhone): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM classic_flow_sessions WHERE user_phone = :phone",
            [':phone' => $userPhone]
        ) ?: null;
    }

    private function saveSession(string $userPhone, int $nodeId): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_TTL_MINUTES * 60);
        $this->db->query(
            "INSERT INTO classic_flow_sessions (user_phone, current_node_id, attempts, expires_at)
             VALUES (:phone, :node_id, 0, :expires_at)
             ON DUPLICATE KEY UPDATE current_node_id = :node_id2, attempts = 0, expires_at = :expires_at2",
            [
                ':phone'       => $userPhone,
                ':node_id'     => $nodeId,
                ':expires_at'  => $expiresAt,
                ':node_id2'    => $nodeId,
                ':expires_at2' => $expiresAt,
            ]
        );
    }

    private function clearSession(string $userPhone): void
    {
        $this->db->query(
            "DELETE FROM classic_flow_sessions WHERE user_phone = :phone",
            [':phone' => $userPhone]
        );
    }

    private function updateAttempts(string $userPhone, int $attempts): void
    {
        $this->db->query(
            "UPDATE classic_flow_sessions SET attempts = :attempts WHERE user_phone = :phone",
            [':attempts' => $attempts, ':phone' => $userPhone]
        );
    }

    private function isExpired(string $expiresAt): bool
    {
        return strtotime($expiresAt) < time();
    }

    private function getFallbackMessage(): string
    {
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'bot_fallback_message'",
            []
        );
        return $row['setting_value'] ?? 'Lo siento, no entendí tu mensaje. Por favor intenta de nuevo o escribe "inicio" para comenzar.';
    }
}
