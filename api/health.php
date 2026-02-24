<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

try {
    $dbStatus = $db->fetchOne('SELECT 1 as ping');
    $health['checks']['database'] = [
        'status' => $dbStatus ? 'up' : 'down',
        'latency_ms' => 0
    ];
} catch (\Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [
        'status' => 'down',
        'error' => $e->getMessage()
    ];
}

try {
    $openaiStatus = $db->fetchOne(
        "SELECT setting_value FROM settings WHERE setting_key = 'openai_status'",
        []
    );
    
    $health['checks']['openai'] = [
        'status' => ($openaiStatus && $openaiStatus['setting_value'] === 'insufficient_funds') ? 'degraded' : 'operational',
        'message' => ($openaiStatus && $openaiStatus['setting_value'] === 'insufficient_funds') ? 'Insufficient funds' : 'OK'
    ];
} catch (\Exception $e) {
    $health['checks']['openai'] = [
        'status' => 'unknown',
        'error' => $e->getMessage()
    ];
}

$conversationCount = 0;
try {
    $count = $db->fetchOne('SELECT COUNT(*) as total FROM conversations WHERE status = "active"');
    $conversationCount = $count['total'] ?? 0;
    $health['checks']['active_conversations'] = $conversationCount;
} catch (\Exception $e) {
    $health['checks']['active_conversations'] = 'error';
}

$diskFree = disk_free_space(__DIR__);
$diskTotal = disk_total_space(__DIR__);
$diskUsagePercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 2);

$health['checks']['disk'] = [
    'free_gb' => round($diskFree / 1024 / 1024 / 1024, 2),
    'usage_percent' => $diskUsagePercent,
    'status' => $diskUsagePercent > 90 ? 'critical' : 'ok'
];

$health['checks']['memory'] = [
    'usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
    'peak_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2)
];

if ($health['status'] !== 'healthy') {
    http_response_code(503);
}

echo json_encode($health, JSON_PRETTY_PRINT);
