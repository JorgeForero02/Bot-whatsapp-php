<?php
/**
 * Front Controller - Funciona en PHP Built-in Server Y Apache/SiteGround
 */

// ============================================================================
// DETECCIÓN DE ENTORNO Y ROUTING DE ASSETS
// ============================================================================

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Para PHP Built-in Server: permitir servir archivos estáticos directamente
if (php_sapi_name() === 'cli-server') {
    $filePath = __DIR__ . $requestUri;
    if ($requestUri !== '/' && is_file($filePath)) {
        return false; // Dejar que PHP sirva el archivo directamente
    }
}

// ============================================================================
// CÁLCULO DE BASE_PATH (para subcarpetas en producción)
// ============================================================================

$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$scriptName = rtrim($scriptName, '/');

if ($scriptName === '' || $scriptName === '.') {
    $basePath = '';
} else {
    $basePath = $scriptName;
}

// Extraer path relativo (sin BASE_PATH)
if ($basePath !== '' && strpos($requestUri, $basePath) === 0) {
    $path = substr($requestUri, strlen($basePath));
} else {
    $path = $requestUri;
}

// Normalizar
$path = '/' . ltrim($path, '/');
if ($path === '//') {
    $path = '/';
}

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\OnboardingService;

session_start();

$config = Config::load(__DIR__ . '/config/config.php');
$db = Database::getInstance(Config::get('database'));
$logger = new Logger(__DIR__ . '/logs');

$requestMethod = $_SERVER['REQUEST_METHOD'];

define('BASE_PATH', $basePath);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$panelPages = ['/', '/conversations', '/documents', '/settings', '/calendar-settings', '/credentials', '/flow-builder'];

if (in_array($path, $panelPages)) {
    try {
        $onboardingService = new OnboardingService($db);
        if (!$onboardingService->isOnboardingComplete()) {
            header('Location: ' . $basePath . '/onboarding');
            exit;
        }
    } catch (\Exception $e) {
    }
}

if ($path === '/onboarding') {
    require __DIR__ . '/views/onboarding.php';
    exit;
}

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

if ($path === '/calendar-settings') {
    require __DIR__ . '/views/calendar_settings.php';
    exit;
}

if ($path === '/credentials') {
    require __DIR__ . '/views/settings-credentials.php';
    exit;
}

if ($path === '/flow-builder') {
    require __DIR__ . '/views/flow-builder.php';
    exit;
}

// ── API routes (con y sin .php) ────────────────────────────────────────────

// ── Webhook de WhatsApp ────────────────────────────────────────────────────
if (($requestMethod === 'GET' || $requestMethod === 'POST') && $path === '/webhook') {
    require __DIR__ . '/webhook.php';
    exit;
}

if ($requestMethod === 'POST' && ($path === '/api/upload' || $path === '/api/upload.php')) {
    require __DIR__ . '/api/upload.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/get-documents' || $path === '/api/get-documents.php')) {
    require __DIR__ . '/api/get-documents.php';
    exit;
}

// DELETE /api/delete-document.php?id=X  (usado por el frontend)
if ($requestMethod === 'DELETE' && ($path === '/api/delete-document' || $path === '/api/delete-document.php')) {
    require __DIR__ . '/api/delete-document.php';
    exit;
}

// DELETE /api/documents/{id}  (ruta REST antigua)
if ($requestMethod === 'DELETE' && preg_match('#^/api/documents/(\d+)$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/api/delete-document.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/get-document-content' || $path === '/api/get-document-content.php')) {
    require __DIR__ . '/api/get-document-content.php';
    exit;
}

if ($requestMethod === 'GET' && preg_match('#^/api/documents/(\d+)/content$#', $path, $matches)) {
    $_GET['id'] = $matches[1];
    require __DIR__ . '/api/get-document-content.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/conversations' || $path === '/api/get-conversations' || $path === '/api/get-conversations.php')) {
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

if ($requestMethod === 'GET' && ($path === '/api/stats' || $path === '/api/get-stats' || $path === '/api/get-stats.php')) {
    require __DIR__ . '/api/get-stats.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/check-updates' || $path === '/api/check-updates.php')) {
    require __DIR__ . '/api/check-updates.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/check-conversation-updates' || $path === '/api/check-conversation-updates.php')) {
    require __DIR__ . '/api/check-conversation-updates.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/check-openai-status' || $path === '/api/check-openai-status.php')) {
    require __DIR__ . '/api/check-openai-status.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/health' || $path === '/api/health.php')) {
    require __DIR__ . '/api/health.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/settings' || $path === '/api/get-settings' || $path === '/api/get-settings.php')) {
    require __DIR__ . '/api/get-settings.php';
    exit;
}

if ($requestMethod === 'POST' && ($path === '/api/settings' || $path === '/api/save-settings' || $path === '/api/save-settings.php')) {
    require __DIR__ . '/api/save-settings.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/get-calendar-settings' || $path === '/api/get-calendar-settings.php')) {
    require __DIR__ . '/api/get-calendar-settings.php';
    exit;
}

if ($requestMethod === 'POST' && ($path === '/api/save-calendar-settings' || $path === '/api/save-calendar-settings.php')) {
    require __DIR__ . '/api/save-calendar-settings.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/credentials' || $path === '/api/get-credentials' || $path === '/api/get-credentials.php')) {
    require __DIR__ . '/api/get-credentials.php';
    exit;
}

if ($requestMethod === 'POST' && ($path === '/api/credentials' || $path === '/api/save-credentials' || $path === '/api/save-credentials.php')) {
    require __DIR__ . '/api/save-credentials.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/test-connection' || $path === '/api/test-connection.php')) {
    require __DIR__ . '/api/test-connection.php';
    exit;
}

if ($requestMethod === 'GET' && ($path === '/api/get-flows' || $path === '/api/get-flows.php')) {
    require __DIR__ . '/api/get-flows.php';
    exit;
}

if ($requestMethod === 'POST' && ($path === '/api/save-flow' || $path === '/api/save-flow.php')) {
    require __DIR__ . '/api/save-flow.php';
    exit;
}

if ($requestMethod === 'DELETE' && ($path === '/api/delete-flow' || $path === '/api/delete-flow.php')) {
    require __DIR__ . '/api/delete-flow.php';
    exit;
}

if ($requestMethod === 'POST' && ($path === '/api/simulate-flow' || $path === '/api/simulate-flow.php')) {
    require __DIR__ . '/api/simulate-flow.php';
    exit;
}

if (($requestMethod === 'GET' || $requestMethod === 'POST') && ($path === '/api/onboarding-progress' || $path === '/api/onboarding-progress.php')) {
    require __DIR__ . '/api/onboarding-progress.php';
    exit;
}

if ($requestMethod === 'POST' && ($path === '/api/onboarding-reset' || $path === '/api/onboarding-reset.php')) {
    require __DIR__ . '/api/onboarding-reset.php';
    exit;
}

http_response_code(404);
echo '404 - Not Found';
