<?php

$allowedIps = ['127.0.0.1', '::1'];
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$token = $_GET['token'] ?? '';
$expectedToken = getenv('SYSTEM_CHECK_TOKEN') ?: '';

if (!in_array($clientIp, $allowedIps) && $token !== $expectedToken) {
    http_response_code(403);
    echo 'Forbidden. Use ?token=YOUR_TOKEN or access from localhost.';
    exit;
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>System Check</title>";
echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}";
echo ".ok{color:green;font-weight:bold;}.error{color:red;font-weight:bold;}";
echo "table{background:white;width:100%;border-collapse:collapse;}";
echo "th,td{padding:10px;border:1px solid #ddd;text-align:left;}";
echo "th{background:#333;color:white;}</style></head><body>";
echo "<h1>🔍 SiteGround System Check</h1>";

echo "<h2>PHP Information</h2>";
echo "<table>";
echo "<tr><th>Item</th><th>Value</th><th>Status</th></tr>";

$php_version = phpversion();
$php_ok = version_compare($php_version, '7.4.0', '>=');
echo "<tr><td>PHP Version</td><td>$php_version</td>";
echo "<td class='" . ($php_ok ? "ok" : "error") . "'>" . ($php_ok ? "✓ OK" : "✗ Version too old") . "</td></tr>";

$memory = ini_get('memory_limit');
echo "<tr><td>Memory Limit</td><td>$memory</td><td class='ok'>✓</td></tr>";

$max_exec = ini_get('max_execution_time');
echo "<tr><td>Max Execution Time</td><td>{$max_exec}s</td><td class='ok'>✓</td></tr>";

$upload_max = ini_get('upload_max_filesize');
echo "<tr><td>Upload Max Filesize</td><td>$upload_max</td><td class='ok'>✓</td></tr>";

echo "</table>";

echo "<h2>Required Extensions</h2>";
echo "<table>";
echo "<tr><th>Extension</th><th>Status</th></tr>";

$required_extensions = ['curl', 'json', 'mbstring', 'mysqli', 'zip', 'xml'];
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<tr><td>$ext</td>";
    echo "<td class='" . ($loaded ? "ok" : "error") . "'>" . ($loaded ? "✓ Loaded" : "✗ Missing") . "</td></tr>";
}

echo "</table>";

echo "<h2>File System</h2>";
echo "<table>";
echo "<tr><th>Item</th><th>Status</th></tr>";

$vendor_exists = is_dir(__DIR__ . '/vendor');
echo "<tr><td>vendor/ directory</td>";
echo "<td class='" . ($vendor_exists ? "ok" : "error") . "'>" . ($vendor_exists ? "✓ Exists" : "✗ Missing - Upload vendor folder") . "</td></tr>";

$autoload_exists = file_exists(__DIR__ . '/vendor/autoload.php');
echo "<tr><td>vendor/autoload.php</td>";
echo "<td class='" . ($autoload_exists ? "ok" : "error") . "'>" . ($autoload_exists ? "✓ Exists" : "✗ Missing") . "</td></tr>";

$env_exists = file_exists(__DIR__ . '/.env');
echo "<tr><td>.env file</td>";
echo "<td class='" . ($env_exists ? "ok" : "error") . "'>" . ($env_exists ? "✓ Exists" : "✗ Missing - Create .env file") . "</td></tr>";

$logs_writable = is_writable(__DIR__ . '/logs');
echo "<tr><td>logs/ writable</td>";
echo "<td class='" . ($logs_writable ? "ok" : "error") . "'>" . ($logs_writable ? "✓ Writable" : "✗ Not writable - Change permissions to 755") . "</td></tr>";

$uploads_writable = is_writable(__DIR__ . '/uploads');
echo "<tr><td>uploads/ writable</td>";
echo "<td class='" . ($uploads_writable ? "ok" : "error") . "'>" . ($uploads_writable ? "✓ Writable" : "✗ Not writable - Change permissions to 755") . "</td></tr>";

echo "</table>";

echo "<h2>Database Connection</h2>";
echo "<table>";
echo "<tr><th>Item</th><th>Status</th></tr>";

if ($env_exists) {
    $env_content = file_get_contents(__DIR__ . '/.env');
    preg_match('/DB_HOST=(.+)/', $env_content, $host);
    preg_match('/DB_NAME=(.+)/', $env_content, $name);
    preg_match('/DB_USER=(.+)/', $env_content, $user);
    preg_match('/DB_PASSWORD=(.+)/', $env_content, $pass);
    
    if (!empty($host[1]) && !empty($name[1]) && !empty($user[1])) {
        $db_host = trim($host[1]);
        $db_name = trim($name[1]);
        $db_user = trim($user[1]);
        $db_pass = isset($pass[1]) ? trim($pass[1]) : '';
        
        try {
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
            if ($conn->connect_error) {
                echo "<tr><td>MySQL Connection</td><td class='error'>✗ Failed: " . $conn->connect_error . "</td></tr>";
            } else {
                echo "<tr><td>MySQL Connection</td><td class='ok'>✓ Connected to $db_name</td></tr>";
                $conn->close();
            }
        } catch (Exception $e) {
            echo "<tr><td>MySQL Connection</td><td class='error'>✗ Error: " . $e->getMessage() . "</td></tr>";
        }
    } else {
        echo "<tr><td>MySQL Connection</td><td class='error'>✗ Database credentials not configured in .env</td></tr>";
    }
} else {
    echo "<tr><td>MySQL Connection</td><td class='error'>✗ Cannot check - .env file missing</td></tr>";
}

echo "</table>";

echo "<h2>Autoload Test</h2>";
echo "<table>";
echo "<tr><th>Item</th><th>Status</th></tr>";

if ($autoload_exists) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "<tr><td>Composer Autoload</td><td class='ok'>✓ Loaded successfully</td></tr>";
        
        $classes_to_check = [
            'GuzzleHttp\\Client',
            'App\\Core\\Config',
            'App\\Core\\Database',
            'App\\Services\\WhatsAppService'
        ];
        
        foreach ($classes_to_check as $class) {
            $exists = class_exists($class);
            echo "<tr><td>$class</td>";
            echo "<td class='" . ($exists ? "ok" : "error") . "'>" . ($exists ? "✓ Found" : "✗ Missing") . "</td></tr>";
        }
    } catch (Exception $e) {
        echo "<tr><td>Composer Autoload</td><td class='error'>✗ Error: " . $e->getMessage() . "</td></tr>";
    }
} else {
    echo "<tr><td>Composer Autoload</td><td class='error'>✗ vendor/autoload.php not found</td></tr>";
}

echo "</table>";

echo "<h2>Webhook Test</h2>";
echo "<table>";
echo "<tr><th>Item</th><th>Status</th></tr>";

$webhook_exists = file_exists(__DIR__ . '/webhook.php');
echo "<tr><td>webhook.php file</td>";
echo "<td class='" . ($webhook_exists ? "ok" : "error") . "'>" . ($webhook_exists ? "✓ Exists" : "✗ Missing") . "</td></tr>";

if ($webhook_exists) {
    $webhook_readable = is_readable(__DIR__ . '/webhook.php');
    echo "<tr><td>webhook.php readable</td>";
    echo "<td class='" . ($webhook_readable ? "ok" : "error") . "'>" . ($webhook_readable ? "✓ Readable" : "✗ Not readable - Check permissions") . "</td></tr>";
}

echo "</table>";

echo "<hr><p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>If all checks are ✓ OK, your system is ready</li>";
echo "<li>Fix any ✗ errors shown above</li>";
echo "<li>Delete this check_system.php file after verification</li>";
echo "<li>Test webhook at: <code>https://yourdomain.com/webhook.php</code></li>";
echo "</ul>";

echo "</body></html>";
