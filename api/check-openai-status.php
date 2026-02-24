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
    $openaiStatus = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'openai_status'",
        []
    );
    
    $status = $openaiStatus['setting_value'] ?? 'active';
    
    echo json_encode([
        'success' => true,
        'status' => $status,
        'can_enable_ai' => $status !== 'insufficient_funds'
    ]);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Check OpenAI Status Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
