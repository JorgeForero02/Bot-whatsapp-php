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
    $lastCheck = $_GET['last_check'] ?? null;

    if (!$lastCheck) {
        throw new \InvalidArgumentException('last_check timestamp is required');
    }

    $updatedConversations = $db->fetchAll(
        'SELECT id, last_message_at, status FROM conversations 
         WHERE last_message_at > :last_check1 OR updated_at > :last_check2
         ORDER BY last_message_at DESC',
        [':last_check1' => $lastCheck, ':last_check2' => $lastCheck]
    );

    echo json_encode([
        'success' => true,
        'has_updates' => count($updatedConversations) > 0,
        'updated_conversations' => $updatedConversations
    ]);

} catch (\Exception $e) {
    if (isset($logger)) {
        $logger->error('Check Conversation Updates Error: ' . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
