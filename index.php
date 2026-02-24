<?php

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $requestUri;

if ($requestUri !== '/' && file_exists($filePath . '.php') && is_file($filePath . '.php')) {
    return false;
}

if ($requestUri !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false;
}

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

session_start();

$config = Config::load(__DIR__ . '/config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/logs');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$path = $requestUri;

if ($path === '' || $path === '/') {
    require __DIR__ . '/views/dashboard.php';
    exit;
}

if ($path === '/conversations') {
    require __DIR__ . '/views/conversations.php';
    exit;
}

if ($path === '/documents') {
    require __DIR__ . '/views/documents.php';
    exit;
}

if ($path === '/settings') {
    require __DIR__ . '/views/settings.php';
    exit;
}

if ($requestMethod === 'POST' && $path === '/api/upload') {
    require __DIR__ . '/api/upload.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/documents') {
    require __DIR__ . '/api/get-documents.php';
    exit;
}

if ($requestMethod === 'DELETE' && preg_match('#^/api/documents/(\d+)$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/api/delete-document.php';
    exit;
}

if ($requestMethod === 'GET' && preg_match('#^/api/documents/(\d+)/content$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/api/get-document-content.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/conversations') {
    require __DIR__ . '/api/get-conversations.php';
    exit;
}

if ($requestMethod === 'GET' && preg_match('#^/api/conversations/(\d+)/messages$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/api/get-conversation-messages.php';
    exit;
}

if ($requestMethod === 'POST' && preg_match('#^/api/conversations/(\d+)/reply$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/api/reply-conversation.php';
    exit;
}

if ($requestMethod === 'POST' && preg_match('#^/api/conversations/(\d+)/toggle-ai$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/api/toggle-ai.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/stats') {
    require __DIR__ . '/api/get-stats.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/check-updates') {
    require __DIR__ . '/api/check-updates.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/check-conversation-updates') {
    require __DIR__ . '/api/check-conversation-updates.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/check-openai-status') {
    require __DIR__ . '/api/check-openai-status.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/health') {
    require __DIR__ . '/api/health.php';
    exit;
}

if ($requestMethod === 'GET' && $path === '/api/settings') {
    require __DIR__ . '/api/get-settings.php';
    exit;
}

if ($requestMethod === 'POST' && $path === '/api/settings') {
    require __DIR__ . '/api/save-settings.php';
    exit;
}

http_response_code(404);
echo '404 - Not Found';
