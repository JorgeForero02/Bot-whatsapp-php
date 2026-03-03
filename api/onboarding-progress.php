<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

use App\Services\OnboardingService;

try {
    $service = new OnboardingService($db);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $service->autoSkipIfNeeded();
        ob_clean();
        echo json_encode([
            'success'    => true,
            'steps'      => $service->getProgress(),
            'current'    => $service->getCurrentStep(),
            'complete'   => $service->isOnboardingComplete(),
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || empty($input['action']) || empty($input['step'])) {
            http_response_code(400);
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Se requieren "action" y "step"']);
            exit;
        }

        switch ($input['action']) {
            case 'complete':
                $service->completeStep($input['step']);
                break;
            case 'skip':
                $service->skipStep($input['step']);
                break;
            default:
                http_response_code(400);
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Acción inválida. Use "complete" o "skip"']);
                exit;
        }

        $service->autoSkipIfNeeded();
        ob_clean();
        echo json_encode([
            'success' => true,
            'steps'   => $service->getProgress(),
            'current' => $service->getCurrentStep(),
            'complete' => $service->isOnboardingComplete(),
        ]);
        exit;
    }

    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);

} catch (\InvalidArgumentException $e) {
    http_response_code(422);
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (\Exception $e) {
    $logger->error('onboarding-progress error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Error en onboarding']);
}
