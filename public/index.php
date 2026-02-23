<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;

session_start();

$config = Config::load(__DIR__ . '/../config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/../logs');

$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

$basePath = '/public';
$path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));

if ($path === '' || $path === '/') {
    require __DIR__ . '/../views/dashboard.php';
    exit;
}

if ($path === '/conversations') {
    require __DIR__ . '/../views/conversations.php';
    exit;
}

if ($path === '/documents') {
    require __DIR__ . '/../views/documents.php';
    exit;
}

if ($path === '/settings') {
    require __DIR__ . '/../views/settings.php';
    exit;
}

if ($path === '/api/upload' && $requestMethod === 'POST') {
    require __DIR__ . '/../api/upload.php';
    exit;
}

if ($path === '/api/documents' && $requestMethod === 'GET') {
    require __DIR__ . '/../api/get-documents.php';
    exit;
}

if (preg_match('#^/api/documents/(\d+)$#', $path, $matches) && $requestMethod === 'DELETE') {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/../api/delete-document.php';
    exit;
}

if (preg_match('#^/api/documents/(\d+)/content$#', $path, $matches) && $requestMethod === 'GET') {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/../api/get-document-content.php';
    exit;
}

if ($path === '/api/conversations' && $requestMethod === 'GET') {
    require __DIR__ . '/../api/get-conversations.php';
    exit;
}

if (preg_match('#^/api/conversations/(\d+)/reply$#', $path, $matches) && $requestMethod === 'POST') {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/../api/reply-conversation.php';
    exit;
}

if (preg_match('#^/api/conversations/(\d+)/ai-toggle$#', $path, $matches) && $requestMethod === 'POST') {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/../api/toggle-ai.php';
    exit;
}

if (preg_match('#^/api/conversations/(\d+)/messages$#', $path, $matches) && $requestMethod === 'GET') {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/../api/get-conversation-messages.php';
    exit;
}

if ($path === '/api/stats' && $requestMethod === 'GET') {
    require __DIR__ . '/../api/get-stats.php';
    exit;
}

if ($path === '/api/check-updates' && $requestMethod === 'GET') {
    require __DIR__ . '/../api/check-updates.php';
    exit;
}

http_response_code(404);
echo '404 Not Found';
