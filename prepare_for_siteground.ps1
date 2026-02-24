Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  Preparando Bot WhatsApp para SiteGround" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

$projectPath = $PSScriptRoot
$timestamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$zipName = "bot_whatsapp_siteground_$timestamp.zip"
$zipPath = Join-Path $projectPath $zipName

Write-Host "Archivo de salida: $zipName" -ForegroundColor Yellow
Write-Host ""

Write-Host "[1/7] Limpiando archivos temporales..." -ForegroundColor Yellow
$tempFiles = @(
    "SOLUCION_ERROR_404.md",
    "CONFIGURAR_SUBCARPETA_WHATSAPP.md",
    "CAMBIOS_SUBCARPETA_APLICADOS.md"
)

Get-ChildItem -Path $projectPath -Filter "*.zip" -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $projectPath -Filter ".htaccess.siteground" -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue
Get-ChildItem -Path $projectPath -Filter ".htaccess.whatsapp_subfolder" -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue

foreach ($pattern in $tempFiles) {
    Get-ChildItem -Path $projectPath -Filter $pattern -ErrorAction SilentlyContinue | Remove-Item -Force -ErrorAction SilentlyContinue
}
Write-Host "OK - Archivos temporales eliminados" -ForegroundColor Green

Write-Host "`n[2/7] Verificando vendor..." -ForegroundColor Yellow
if (-not (Test-Path "$projectPath\vendor")) {
    Write-Host "ERROR: La carpeta vendor/ no existe!" -ForegroundColor Red
    Write-Host "  Debes ejecutar 'composer install' primero." -ForegroundColor Red
    exit 1
}

if (-not (Test-Path "$projectPath\vendor\autoload.php")) {
    Write-Host "ERROR: vendor/autoload.php no existe!" -ForegroundColor Red
    Write-Host "  Ejecuta 'composer install' o 'composer dump-autoload'" -ForegroundColor Red
    exit 1
}
Write-Host "OK - vendor/ verificado correctamente" -ForegroundColor Green

Write-Host "`n[3/7] Verificando archivos criticos..." -ForegroundColor Yellow
$criticalFiles = @{
    "webhook.php" = "Endpoint principal WhatsApp"
    "index.php" = "Router de la aplicacion"
    "check_system.php" = "Herramienta de diagnostico"
    "config\config.php" = "Configuracion"
    "database\schema.sql" = "Schema de base de datos"
    "vendor\guzzlehttp" = "Cliente HTTP"
    "src\Services\WhatsAppService.php" = "Servicio WhatsApp"
    "src\Services\GoogleCalendarService.php" = "Servicio Calendar"
    "src\Services\OpenAIService.php" = "Servicio OpenAI"
    "views\layout.php" = "Template base"
}

$allGood = $true
foreach ($file in $criticalFiles.Keys) {
    if (-not (Test-Path "$projectPath\$file")) {
        Write-Host "  ERROR: Falta $file ($($criticalFiles[$file]))" -ForegroundColor Red
        $allGood = $false
    }
}

if (-not $allGood) {
    Write-Host "`nERROR: Faltan archivos criticos" -ForegroundColor Red
    exit 1
}
Write-Host "OK - Todos los archivos criticos verificados" -ForegroundColor Green

Write-Host "`n[4/7] Verificando .htaccess..." -ForegroundColor Yellow
if (Test-Path "$projectPath\.htaccess") {
    $htaccessContent = Get-Content "$projectPath\.htaccess" -Raw
    if ($htaccessContent -match "RewriteEngine On") {
        Write-Host "OK - .htaccess verificado" -ForegroundColor Green
    } else {
        Write-Host "ADVERTENCIA: .htaccess sin RewriteEngine" -ForegroundColor Yellow
    }
} else {
    Write-Host "ERROR: .htaccess no encontrado" -ForegroundColor Red
    exit 1
}

Write-Host "`n[5/7] Verificando estructura de carpetas..." -ForegroundColor Yellow
$requiredFolders = @("logs", "uploads", "uploads\audios", "uploads\documents")
foreach ($folder in $requiredFolders) {
    $folderPath = Join-Path $projectPath $folder
    if (-not (Test-Path $folderPath)) {
        New-Item -ItemType Directory -Path $folderPath -Force | Out-Null
        Write-Host "  + Creada: $folder" -ForegroundColor Cyan
    }
}
Write-Host "OK - Estructura de carpetas lista" -ForegroundColor Green

Write-Host "`n[6/7] Creando archivo ZIP..." -ForegroundColor Yellow

$filesToInclude = @(
    "vendor",
    "src",
    "config",
    "database",
    "api",
    "views",
    "workers",
    "logs",
    "uploads",
    "webhook.php",
    "index.php",
    "check_system.php",
    "prepare_for_siteground.ps1",
    ".htaccess",
    ".gitignore",
    "composer.json",
    "DEPLOY_SITEGROUND.md",
    "ESTRUCTURA_PROYECTO.md"
)

$excludePatterns = @(
    ".git",
    ".gitattributes",
    ".env",
    "*.log",
    "*.zip",
    "*.md.backup",
    ".htaccess.siteground",
    ".htaccess.whatsapp_subfolder",
    "SOLUCION_ERROR_404.md",
    "CONFIGURAR_SUBCARPETA_WHATSAPP.md",
    "CAMBIOS_SUBCARPETA_APLICADOS.md",
    "node_modules",
    ".idea",
    ".vscode",
    "*.tmp"
)

Write-Host "Comprimiendo archivos (esto puede tardar unos minutos)..." -ForegroundColor Cyan

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')

foreach ($item in $filesToInclude) {
    $fullPath = Join-Path $projectPath $item
    if (Test-Path $fullPath) {
        if (Test-Path $fullPath -PathType Container) {
            $files = Get-ChildItem -Path $fullPath -Recurse -File
            foreach ($file in $files) {
                $relativePath = $file.FullName.Substring($projectPath.Length + 1)
                
                $skip = $false
                foreach ($pattern in $excludePatterns) {
                    if ($relativePath -like "*$pattern*") {
                        $skip = $true
                        break
                    }
                }
                
                if (-not $skip) {
                    $entryName = $relativePath.Replace('\', '/')
                    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName) | Out-Null
                }
            }
        } else {
            $relativePath = $item
            $entryName = $relativePath.Replace('\', '/')
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $fullPath, $entryName) | Out-Null
        }
    }
}

$zip.Dispose()

Write-Host "OK - ZIP creado: $zipName" -ForegroundColor Green

Write-Host "`n[7/7] Finalizando..." -ForegroundColor Yellow
$zipSize = (Get-Item $zipPath).Length / 1MB
$fileCount = (Get-ChildItem -Path $projectPath -Recurse -File | Measure-Object).Count
Write-Host "OK - ZIP creado exitosamente" -ForegroundColor Green
Write-Host "  Tamano: $([math]::Round($zipSize, 2)) MB" -ForegroundColor Cyan
Write-Host "  Archivos incluidos: ~$fileCount" -ForegroundColor Cyan

Write-Host "`n================================================" -ForegroundColor Green
Write-Host "  PAQUETE LISTO PARA DEPLOYMENT" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Archivo generado:" -ForegroundColor Cyan
Write-Host "   $zipName" -ForegroundColor Yellow
Write-Host ""
Write-Host "INSTRUCCIONES DE DEPLOYMENT:" -ForegroundColor Cyan
Write-Host ""
Write-Host "[1] Subir a SiteGround" -ForegroundColor White
Write-Host "    - File Manager -> public_html/[carpeta]" -ForegroundColor Gray
Write-Host "    - Upload: $zipName" -ForegroundColor Gray
Write-Host ""
Write-Host "[2] Extraer ZIP" -ForegroundColor White
Write-Host "    - Click derecho -> Extract" -ForegroundColor Gray
Write-Host "    - Borrar el ZIP despues" -ForegroundColor Gray
Write-Host ""
Write-Host "[3] Configurar" -ForegroundColor White
Write-Host "    - Crear .env con credenciales" -ForegroundColor Gray
Write-Host "    - Importar database/schema.sql" -ForegroundColor Gray
Write-Host "    - Permisos: 755 carpetas, 644 archivos" -ForegroundColor Gray
Write-Host ""
Write-Host "[4] Verificar" -ForegroundColor White
Write-Host "    - https://tudominio.com/[carpeta]/check_system.php" -ForegroundColor Gray
Write-Host "    - Todo debe estar en verde" -ForegroundColor Gray
Write-Host ""
Write-Host "Documentacion completa:" -ForegroundColor Cyan
Write-Host "   - DEPLOY_SITEGROUND.md" -ForegroundColor Yellow
Write-Host "   - ESTRUCTURA_PROYECTO.md" -ForegroundColor Yellow
Write-Host ""
Write-Host "Listo para produccion!" -ForegroundColor Green
Write-Host ""
