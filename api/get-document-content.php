<?php

if (ob_get_level()) ob_end_clean();
ob_start();

require_once __DIR__ . '/bootstrap.php';

try {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        throw new \InvalidArgumentException('Document ID is required');
    }

    $chunks = $db->fetchAll(
        'SELECT chunk_text, chunk_index FROM vectors WHERE document_id = :id ORDER BY chunk_index ASC',
        [':id' => $id]
    );

    $content = implode("\n\n---\n\n", array_column($chunks, 'chunk_text'));

    ob_clean();
    echo json_encode([
        'success'     => true,
        'chunks'      => $chunks,
        'content'     => $content,
        'chunk_count' => count($chunks)
    ]);

} catch (\Exception $e) {
    $logger->error('Get Document Content Error: ' . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener contenido del documento'
    ]);
}
