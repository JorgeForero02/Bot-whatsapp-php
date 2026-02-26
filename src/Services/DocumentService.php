<?php

namespace App\Services;

use App\Core\Database;
use App\Utils\TextProcessor;

class DocumentService
{
    private $db;
    private $uploadPath;
    private $allowedTypes;
    private $maxSize;

    public function __construct(Database $db, $uploadPath, $allowedTypes, $maxSize)
    {
        $this->db = $db;
        $this->uploadPath = $uploadPath;
        $this->allowedTypes = $allowedTypes;
        $this->maxSize = $maxSize;

        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    public function uploadDocument($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload failed with error code: ' . $file['error']);
        }

        if ($file['size'] > $this->maxSize) {
            throw new \RuntimeException('File size exceeds maximum allowed size');
        }

        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->allowedTypes)) {
            throw new \RuntimeException('File type not allowed: ' . $extension);
        }

        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $this->uploadPath . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \RuntimeException('Failed to move uploaded file');
        }

        try {
            $text = TextProcessor::extractText($filepath, $extension);
            $fileHash = hash_file('md5', $filepath);
            
            $existing = $this->db->fetchOne(
                'SELECT id, original_name FROM documents WHERE file_hash = :hash',
                [':hash' => $fileHash]
            );
            
            if ($existing) {
                unlink($filepath);
                throw new \RuntimeException('Documento duplicado: "' . $existing['original_name'] . '" ya fue subido previamente');
            }
            
            $documentId = $this->db->insert('documents', [
                'filename' => $filename,
                'original_name' => $originalName,
                'file_type' => $extension,
                'content_text' => $text,
                'file_size' => $file['size'],
                'file_hash' => $fileHash
            ]);

            return [
                'id' => $documentId,
                'filename' => $filename,
                'original_name' => $originalName,
                'file_type' => $extension,
                'file_size' => $file['size'],
                'text' => $text
            ];

        } catch (\Exception $e) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            throw $e;
        }
    }

    public function getDocument($id)
    {
        return $this->db->fetchOne('SELECT * FROM documents WHERE id = :id', [':id' => $id]);
    }

    public function getAllDocuments($limit = 100)
    {
        return $this->db->fetchAll(
            'SELECT * FROM documents ORDER BY created_at DESC LIMIT :limit',
            [':limit' => $limit]
        );
    }

    public function deleteDocument($id)
    {
        $document = $this->getDocument($id);
        
        if (!$document) {
            throw new \RuntimeException('Document not found');
        }

        $filepath = $this->uploadPath . '/' . $document['filename'];
        
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        return $this->db->delete('documents', 'id = :id', [':id' => $id]);
    }

    public function updateChunkCount($documentId, $count)
    {
        return $this->db->update(
            'documents',
            ['chunk_count' => $count],
            'id = :id',
            [':id' => $documentId]
        );
    }

    public function getDocumentStats()
    {
        $stats = [];
        
        $stats['total'] = $this->db->fetchOne('SELECT COUNT(*) as count FROM documents')['count'] ?? 0;
        $stats['total_size'] = $this->db->fetchOne('SELECT SUM(file_size) as size FROM documents')['size'] ?? 0;
        
        $typeStats = $this->db->fetchAll('SELECT file_type, COUNT(*) as count FROM documents GROUP BY file_type');
        $stats['by_type'] = [];
        foreach ($typeStats as $row) {
            $stats['by_type'][$row['file_type']] = $row['count'];
        }
        
        return $stats;
    }
}
