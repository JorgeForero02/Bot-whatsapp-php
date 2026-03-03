<?php
/**
 * Limpieza automática de archivos de audio antiguos.
 *
 * Elimina archivos de audio con más de 7 días de antigüedad
 * en uploads/audios/{contacto}_{phone}/ y remueve subdirectorios vacíos.
 *
 * Uso como cron job (ejecutar diariamente):
 *   0 3 * * * php /ruta/al/proyecto/workers/cleanup-audios.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Logger;

$logger = new Logger(__DIR__ . '/../logs');
$audiosDir = __DIR__ . '/../uploads/audios';

if (!is_dir($audiosDir)) {
    $logger->info('Cleanup-audios: Directory does not exist, nothing to clean', ['path' => $audiosDir]);
    exit(0);
}

$maxAgeDays = 7;
$cutoffTime = time() - ($maxAgeDays * 86400);
$deletedFiles = 0;
$deletedDirs = 0;
$extensions = ['ogg', 'mp3', 'm4a', 'wav'];

$dirIterator = new RecursiveDirectoryIterator($audiosDir, RecursiveDirectoryIterator::SKIP_DOTS);
$iterator = new RecursiveIteratorIterator($dirIterator, RecursiveIteratorIterator::CHILD_FIRST);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $extensions) && $file->getMTime() < $cutoffTime) {
            $path = $file->getRealPath();
            if (unlink($path)) {
                $deletedFiles++;
            } else {
                $logger->warning('Cleanup-audios: Failed to delete file', ['path' => $path]);
            }
        }
    }
}

$subDirs = new DirectoryIterator($audiosDir);
foreach ($subDirs as $dir) {
    if ($dir->isDot() || !$dir->isDir()) {
        continue;
    }

    $subDirPath = $dir->getRealPath();
    $isEmpty = !(new \FilesystemIterator($subDirPath))->valid();

    if ($isEmpty) {
        if (rmdir($subDirPath)) {
            $deletedDirs++;
        } else {
            $logger->warning('Cleanup-audios: Failed to remove empty directory', ['path' => $subDirPath]);
        }
    }
}

$logger->info('Cleanup-audios: Completed', [
    'deleted_files' => $deletedFiles,
    'deleted_dirs' => $deletedDirs
]);

echo "Cleanup complete: {$deletedFiles} files deleted, {$deletedDirs} empty directories removed.\n";
