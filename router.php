<?php
/**
 * Router para PHP Built-in Server (solo desarrollo local)
 * Uso: php -S localhost:8080 router.php
 * 
 * NO subir a producción - solo para desarrollo local
 */

$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);
$filePath = __DIR__ . $requestPath;

// Servir archivos estáticos directamente si existen
if ($requestPath !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false;
}

// Delegar todo lo demás a index.php
require __DIR__ . '/index.php';
