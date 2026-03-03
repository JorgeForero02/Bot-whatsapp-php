<?php
/**
 * download-vendor.php — One-time script to self-host frontend assets.
 *
 * Run once after deploying to production, then DELETE this file.
 * Visit: https://yourdomain.com/download-vendor.php
 *
 * Downloads:
 *   - Tailwind CSS  → assets/css/tailwind.min.css
 *   - Chart.js UMD  → assets/js/vendor/chart.umd.min.js
 */

// ── Basic security: only allow from command-line or with token ──────────────
if (PHP_SAPI !== 'cli') {
    $token = $_GET['token'] ?? '';
    $expectedToken = getenv('DOWNLOAD_TOKEN') ?: 'download-assets-now';
    if ($token !== $expectedToken) {
        http_response_code(403);
        die('403 Forbidden. Add ?token=download-assets-now to the URL.');
    }
}

// ── Asset definitions ───────────────────────────────────────────────────────
$assets = [
    [
        'name'  => 'Tailwind CSS 3.4.1 (full)',
        'urls'  => [
            'https://cdn.jsdelivr.net/npm/tailwindcss@3.4.1/dist/tailwind.min.css',
            'https://unpkg.com/tailwindcss@3.4.1/dist/tailwind.min.css',
        ],
        'dest'  => __DIR__ . '/assets/css/tailwind.min.css',
        'min_size' => 50000,
    ],
    [
        'name'  => 'Chart.js 4.4.4 UMD',
        'urls'  => [
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js',
            'https://unpkg.com/chart.js@4.4.4/dist/chart.umd.min.js',
        ],
        'dest'  => __DIR__ . '/assets/js/vendor/chart.umd.min.js',
        'min_size' => 50000,
    ],
];

// ── Create directories ──────────────────────────────────────────────────────
@mkdir(__DIR__ . '/assets/css',        0755, true);
@mkdir(__DIR__ . '/assets/js/vendor',  0755, true);

// ── HTML output ─────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
echo '<title>Download Vendor Assets</title>';
echo '<style>body{font-family:monospace;padding:2rem;max-width:800px;margin:0 auto;}';
echo '.ok{color:#16a34a;} .err{color:#dc2626;} .info{color:#2563eb;}';
echo 'pre{background:#f3f4f6;padding:0.75rem;border-radius:0.375rem;overflow:auto;font-size:0.8rem;}';
echo 'h1{margin-bottom:1.5rem;} h2{margin-top:1.5rem;}</style></head><body>';
echo '<h1>Download Vendor Assets</h1>';
echo '<p>Downloading assets to self-host. Please wait...</p><hr>';

flush();

// ── Download function ───────────────────────────────────────────────────────
function downloadUrl($url)
{
    // Try curl first (more reliable for SSL on shared hosting)
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 PHP/' . PHP_VERSION,
            CURLOPT_HTTPHEADER     => ['Accept: */*'],
        ]);
        $content  = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($content !== false && $httpCode === 200 && strlen($content) > 0) {
            return [$content, null];
        }
        $curlMsg = "curl: HTTP {$httpCode}" . ($curlErr ? " — {$curlErr}" : '');
    } else {
        $curlMsg = 'curl not available';
    }

    // Fallback: file_get_contents
    $ctx = stream_context_create([
        'http' => [
            'timeout'    => 30,
            'user_agent' => 'Mozilla/5.0 PHP/' . PHP_VERSION,
        ],
        'ssl'  => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);
    $content = @file_get_contents($url, false, $ctx);
    if ($content !== false && strlen($content) > 0) {
        return [$content, null];
    }

    return [false, $curlMsg . '; file_get_contents also failed'];
}

// ── Process each asset ──────────────────────────────────────────────────────
$allOk = true;

foreach ($assets as $asset) {
    echo "<h2>{$asset['name']}</h2>";

    $destExists = file_exists($asset['dest']);
    if ($destExists) {
        $size = number_format(filesize($asset['dest']));
        echo "<p class='info'>Already exists ({$size} bytes). Re-downloading to update.</p>";
    }

    $downloaded = false;
    foreach ($asset['urls'] as $url) {
        echo "<p>Trying: <code>{$url}</code> ... </p>";
        flush();

        list($content, $err) = downloadUrl($url);

        if ($content !== false && strlen($content) >= $asset['min_size']) {
            $bytes = strlen($content);
            if (file_put_contents($asset['dest'], $content) !== false) {
                echo "<p class='ok'>✓ Saved {$bytes} bytes → <code>{$asset['dest']}</code></p>";
                $downloaded = true;
                break;
            } else {
                echo "<p class='err'>✗ Downloaded but could not write file. Check directory permissions (755).</p>";
            }
        } else {
            $detail = $err ?: ('Got ' . strlen($content ?: '') . ' bytes, expected >= ' . $asset['min_size']);
            echo "<p class='err'>✗ Failed ({$detail}). Trying next URL...</p>";
        }
        flush();
    }

    if (!$downloaded) {
        echo "<p class='err'><strong>✗ All URLs failed for {$asset['name']}. CDN fallback will be used.</strong></p>";
        $allOk = false;
    }
}

// ── Summary ─────────────────────────────────────────────────────────────────
echo '<hr><h2>Summary</h2>';
foreach ($assets as $asset) {
    $exists = file_exists($asset['dest']);
    $size   = $exists ? number_format(filesize($asset['dest'])) . ' bytes' : 'missing';
    $cls    = $exists ? 'ok' : 'err';
    $icon   = $exists ? '✓' : '✗';
    $rel    = str_replace(__DIR__ . '/', '', $asset['dest']);
    echo "<p class='{$cls}'>{$icon} {$asset['name']} — <code>{$rel}</code> ({$size})</p>";
}

if ($allOk) {
    echo '<p class="ok"><strong>All assets downloaded. The app will now use local files instead of CDN.</strong></p>';
} else {
    echo '<p class="err"><strong>Some assets failed. The app will fall back to CDN for missing files.</strong></p>';
}

echo '<hr>';
echo '<p><strong style="color:#b91c1c;">IMPORTANT: Delete this file from the server after use!</strong><br>';
echo '<code>rm ' . basename(__FILE__) . '</code></p>';
echo '</body></html>';
