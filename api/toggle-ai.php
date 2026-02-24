<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

header('Content-Type: application/json');

try {
    $id = $_GET['id'] ?? null;
    $input = json_decode(file_get_contents('php://input'), true);
    $aiEnabled = $input['ai_enabled'] ?? null;

    if (!$id || $aiEnabled === null) {
        throw new \InvalidArgumentException('Conversation ID and ai_enabled state required');
    }

    if ($aiEnabled) {
        $openaiStatus = $db->fetchOne(
            "SELECT setting_value FROM settings WHERE setting_key = 'openai_status'",
            []
        );
        
        if ($openaiStatus && $openaiStatus['setting_value'] === 'insufficient_funds') {
            http_response_code(402);
            echo json_encode([
                'success' => false,
                'error' => 'INSUFFICIENT_FUNDS',
                'message' => 'No se puede activar la IA. Fondos insuficientes en OpenAI.'
            ]);
            exit;
        }
    }

    $db->query(
        'UPDATE conversations SET ai_enabled = :ai_enabled WHERE id = :id',
        [
            ':ai_enabled' => $aiEnabled ? 1 : 0,
            ':id' => $id
        ]
    );

    echo json_encode([
        'success' => true,
        'message' => 'AI state updated successfully'
    ]);

} catch (\Exception $e) {
    $logger->error('Toggle AI Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
