<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\OnboardingService;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $service = new OnboardingService($db);
    $service->resetOnboarding();

    $logger->info('Onboarding reset by admin');

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Onboarding reiniciado correctamente',
        'steps'   => $service->getProgress(),
    ]);

} catch (\Exception $e) {
    $logger->error('onboarding-reset error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error al reiniciar onboarding']);
}
