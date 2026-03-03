<?php

namespace App\Services;

use App\Core\Database;

class OnboardingService
{
    private Database $db;

    private array $optionalSteps = ['openai_credentials', 'calendar_setup', 'flow_builder'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getCurrentStep(): string
    {
        $steps = $this->getProgress();
        foreach ($steps as $step) {
            if (!$step['is_completed'] && !$step['is_skipped']) {
                return $step['step_name'];
            }
        }
        return 'complete';
    }

    public function completeStep(string $stepName): void
    {
        $order = $this->stepOrder($stepName);
        $this->db->query(
            "INSERT INTO onboarding_progress (step_name, step_order, is_completed, is_skipped, completed_at)
             VALUES (:step, :order, 1, 0, NOW())
             ON DUPLICATE KEY UPDATE is_completed = 1, is_skipped = 0, completed_at = NOW()",
            [':step' => $stepName, ':order' => $order]
        );
    }

    public function skipStep(string $stepName): void
    {
        if (!in_array($stepName, $this->optionalSteps, true)) {
            throw new \InvalidArgumentException("El paso \"{$stepName}\" no puede omitirse.");
        }
        $order = $this->stepOrder($stepName);
        $this->db->query(
            "INSERT INTO onboarding_progress (step_name, step_order, is_completed, is_skipped, completed_at)
             VALUES (:step, :order, 0, 1, NULL)
             ON DUPLICATE KEY UPDATE is_skipped = 1, is_completed = 0, completed_at = NULL",
            [':step' => $stepName, ':order' => $order]
        );
    }

    public function getProgress(): array
    {
        $this->ensureStepsSeeded();
        return $this->db->fetchAll(
            "SELECT step_name, step_order, is_completed, is_skipped, completed_at
             FROM onboarding_progress
             ORDER BY step_order ASC",
            []
        ) ?? [];
    }

    private function ensureStepsSeeded(): void
    {
        $count = $this->db->fetchOne("SELECT COUNT(*) as total FROM onboarding_progress", []);
        if ($count && (int)$count['total'] > 0) {
            return;
        }
        $steps = [
            'whatsapp_credentials' => 1,
            'openai_credentials'   => 2,
            'bot_personality'      => 3,
            'calendar_setup'       => 4,
            'flow_builder'         => 5,
            'test_connection'      => 6,
            'go_live'              => 7,
        ];
        foreach ($steps as $name => $order) {
            $this->db->query(
                "INSERT INTO onboarding_progress (step_name, step_order, is_completed, is_skipped)
                 VALUES (:step, :order, 0, 0)
                 ON DUPLICATE KEY UPDATE step_order = :order2",
                [':step' => $name, ':order' => $order, ':order2' => $order]
            );
        }
    }

    public function isOnboardingComplete(): bool
    {
        $total = $this->db->fetchOne(
            "SELECT COUNT(*) as total FROM onboarding_progress",
            []
        );
        if (!$total || (int)$total['total'] === 0) {
            return false;
        }
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) as pending
             FROM onboarding_progress
             WHERE is_completed = 0 AND is_skipped = 0",
            []
        );
        return isset($row['pending']) && (int)$row['pending'] === 0;
    }

    public function resetOnboarding(): void
    {
        $this->db->query(
            "UPDATE onboarding_progress SET is_completed = 0, is_skipped = 0, completed_at = NULL",
            []
        );
        $this->ensureStepsSeeded();
    }

    public function autoSkipIfNeeded(): void
    {
        try {
            $botMode = $this->getSetting('bot_mode', 'ai');

            // Auto-skip calendar_setup only if calendar is disabled AND user hasn't touched this step yet
            $calRow = $this->db->fetchOne(
                "SELECT is_completed, is_skipped FROM onboarding_progress WHERE step_name = 'calendar_setup'", []
            );
            if ($calRow && !$calRow['is_completed'] && !$calRow['is_skipped']) {
                $calEnabled = $this->getSetting('calendar_enabled', 'false');
                if ($calEnabled !== 'true' && $calEnabled !== '1') {
                    $this->skipStep('calendar_setup');
                }
            }

            // Auto-skip flow_builder in AI mode only if user hasn't touched this step yet
            if ($botMode !== 'classic') {
                $row = $this->db->fetchOne(
                    "SELECT is_completed, is_skipped FROM onboarding_progress WHERE step_name = 'flow_builder'", []
                );
                if ($row && !$row['is_completed'] && !$row['is_skipped']) {
                    $this->skipStep('flow_builder');
                }
            }

            // Auto-skip openai_credentials in classic mode only if user hasn't touched this step yet
            if ($botMode === 'classic') {
                $row = $this->db->fetchOne(
                    "SELECT is_completed, is_skipped FROM onboarding_progress WHERE step_name = 'openai_credentials'", []
                );
                if ($row && !$row['is_completed'] && !$row['is_skipped']) {
                    $this->skipStep('openai_credentials');
                }
            }

        } catch (\Throwable $e) {
        }
    }

    public function autoDetectProgress(): void
    {
        $waCreds = $this->db->fetchOne(
            "SELECT whatsapp_access_token FROM bot_credentials WHERE id = 1",
            []
        );
        if ($waCreds && !empty($waCreds['whatsapp_access_token'])) {
            $this->completeStep('whatsapp_credentials');
        }

        $botMode = $this->getSetting('bot_mode', 'ai');
        if ($botMode === 'classic') {
            $this->skipStep('openai_credentials');
        } else {
            $oaiCreds = $this->db->fetchOne(
                "SELECT openai_api_key FROM bot_credentials WHERE id = 1",
                []
            );
            if ($oaiCreds && !empty($oaiCreds['openai_api_key'])) {
                $this->completeStep('openai_credentials');
            }
        }

        $promptRow = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'system_prompt'",
            []
        );
        if ($promptRow && !empty($promptRow['setting_value'])) {
            $this->completeStep('bot_personality');
        }

        $calEnabled = $this->getSetting('calendar_enabled', 'false');
        if ($calEnabled !== 'true' && $calEnabled !== '1') {
            $this->skipStep('calendar_setup');
        } else {
            $gCreds = $this->db->fetchOne(
                "SELECT access_token FROM google_oauth_credentials WHERE id = 1",
                []
            );
            if ($gCreds && !empty($gCreds['access_token'])) {
                $this->completeStep('calendar_setup');
            }
        }

        if ($botMode !== 'classic') {
            $this->skipStep('flow_builder');
        } else {
            $nodeCount = $this->db->fetchOne(
                "SELECT COUNT(*) as cnt FROM flow_nodes WHERE is_active = 1",
                []
            );
            if ($nodeCount && (int)$nodeCount['cnt'] > 0) {
                $this->completeStep('flow_builder');
            }
        }
    }

    private function stepOrder(string $stepName): int
    {
        $orders = [
            'whatsapp_credentials' => 1,
            'openai_credentials'   => 2,
            'bot_personality'      => 3,
            'calendar_setup'       => 4,
            'flow_builder'         => 5,
            'test_connection'      => 6,
            'go_live'              => 7,
        ];
        return $orders[$stepName] ?? 99;
    }

    private function getSetting(string $key, string $default): string
    {
        $row = $this->db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = :key",
            [':key' => $key]
        );
        return $row['setting_value'] ?? $default;
    }
}
