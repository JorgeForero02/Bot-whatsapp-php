# ============================================================
# prepare_for_siteground.ps1
# Empaqueta el proyecto para subir a SiteGround
# Se aloja en subcarpeta /whatsapp dentro de public_html
# Uso: .\prepare_for_siteground.ps1
# ============================================================

$ProjectRoot = $PSScriptRoot
$Timestamp   = Get-Date -Format "yyyyMMdd_HHmmss"
$ZipName     = "bot_whatsapp_siteground_$Timestamp.zip"
$ZipPath     = Join-Path $ProjectRoot $ZipName

Write-Host ""
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host "  Bot WhatsApp PHP - Preparando para SiteGround" -ForegroundColor Cyan
Write-Host "  Destino: public_html/whatsapp/"                -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""

# ── Verificar archivos críticos ────────────────────────────
$required = @(
    "vendor/autoload.php",
    "composer.json",
    "index.php",
    "webhook.php",
    ".htaccess",
    "config/config.php",
    "database/schema.sql"
)
$missing = @()
foreach ($f in $required) {
    if (-not (Test-Path (Join-Path $ProjectRoot $f))) {
        $missing += $f
    }
}
if ($missing.Count -gt 0) {
    Write-Host "ERROR: Faltan archivos criticos:" -ForegroundColor Red
    $missing | ForEach-Object { Write-Host "  - $_" -ForegroundColor Red }
    exit 1
}
Write-Host "Archivos criticos: OK" -ForegroundColor Green

# ── Archivos y carpetas a incluir ─────────────────────────
$includes = @(
    "vendor",
    "src",
    "api",
    "views",
    "assets",
    "config",
    "database",
    "workers",
    "prompts",
    "index.php",
    "webhook.php",
    "router.php",
    "check_system.php",
    ".htaccess",
    "composer.json"
)

Write-Host "Creando ZIP: $ZipName ..." -ForegroundColor Yellow

try {
    $TempDir = Join-Path $env:TEMP "bot_whatsapp_deploy_$Timestamp"
    New-Item -ItemType Directory -Path $TempDir -Force | Out-Null

    foreach ($item in $includes) {
        $src = Join-Path $ProjectRoot $item
        $dst = Join-Path $TempDir $item
        if (Test-Path $src) {
            if ((Get-Item $src).PSIsContainer) {
                Copy-Item -Path $src -Destination $dst -Recurse -Force
            } else {
                $dstDir = Split-Path $dst -Parent
                if (-not (Test-Path $dstDir)) { New-Item -ItemType Directory -Path $dstDir -Force | Out-Null }
                Copy-Item -Path $src -Destination $dst -Force
            }
        }
    }

    # Crear carpetas necesarias vacías
    New-Item -ItemType Directory -Path (Join-Path $TempDir "logs")            -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $TempDir "uploads\audios")  -Force | Out-Null
    New-Item -ItemType File      -Path (Join-Path $TempDir "logs\.gitkeep")   -Force | Out-Null
    New-Item -ItemType File      -Path (Join-Path $TempDir "uploads\.gitkeep") -Force | Out-Null

    # Comprimir
    Compress-Archive -Path "$TempDir\*" -DestinationPath $ZipPath -Force
    Remove-Item -Path $TempDir -Recurse -Force

    $size = [math]::Round((Get-Item $ZipPath).Length / 1MB, 2)
    Write-Host "ZIP creado: $ZipName ($size MB)" -ForegroundColor Green

} catch {
    Write-Host "ERROR al crear ZIP: $_" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host "  PASOS PARA SUBIR A SITEGROUND"                  -ForegroundColor Cyan
Write-Host "=================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. Sube $ZipName via File Manager de SiteGround"  -ForegroundColor White
Write-Host "   -> Destino: public_html/whatsapp/"              -ForegroundColor Gray
Write-Host ""
Write-Host "2. Extrae el ZIP dentro de public_html/whatsapp/"  -ForegroundColor White
Write-Host "   (todos los archivos deben quedar directamente"  -ForegroundColor Gray
Write-Host "    en public_html/whatsapp/, no en subcarpeta)"   -ForegroundColor Gray
Write-Host ""
Write-Host "3. Crea .env en public_html/whatsapp/ con:"        -ForegroundColor White
Write-Host "   DB_HOST=localhost"                              -ForegroundColor Gray
Write-Host "   DB_NAME=tu_base_de_datos"                       -ForegroundColor Gray
Write-Host "   DB_USER=tu_usuario"                             -ForegroundColor Gray
Write-Host "   DB_PASSWORD=tu_password"                        -ForegroundColor Gray
Write-Host "   APP_BASE_URL=https://tudominio.com/whatsapp"    -ForegroundColor Gray
Write-Host "   APP_TIMEZONE=America/Bogota"                    -ForegroundColor Gray
Write-Host ""
Write-Host "4. Importa database/schema.sql en MySQL"           -ForegroundColor White
Write-Host ""
Write-Host "5. Permisos (via SSH o File Manager):"             -ForegroundColor White
Write-Host "   chmod 755 logs/ uploads/ vendor/ src/ api/"     -ForegroundColor Gray
Write-Host "   chmod 644 .htaccess webhook.php index.php"      -ForegroundColor Gray
Write-Host "   chmod 600 .env"                                 -ForegroundColor Gray
Write-Host ""
Write-Host "6. Webhook URL en Meta/WhatsApp Business:"         -ForegroundColor White
Write-Host "   https://tudominio.com/whatsapp/webhook"         -ForegroundColor Gray
Write-Host ""
Write-Host "7. URL del panel admin:"                           -ForegroundColor White
Write-Host "   https://tudominio.com/whatsapp/"                -ForegroundColor Gray
Write-Host ""
Write-Host "Listo!" -ForegroundColor Green
Write-Host ""
