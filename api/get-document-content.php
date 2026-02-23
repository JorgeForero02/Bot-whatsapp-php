<?php

header('Content-Type: application/json');

use App\Core\Database;
use App\Core\Config;

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new \InvalidArgumentException('Document ID is required');
    }

    $chunks = $db->fetchAll(
        'SELECT chunk_text, chunk_index FROM vectors WHERE document_id = :id ORDER BY chunk_index ASC',
        [':id' => $id]
    );

    echo json_encode([
        'success' => true,
        'chunks' => $chunks
    ]);

} catch (\Exception $e) {
    $logger->error('Get Document Content Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
