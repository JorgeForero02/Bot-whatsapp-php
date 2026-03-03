<?php

namespace App\Services;

use App\Core\Database;
use App\Utils\VectorMath;

class VectorSearchService
{
    private $db;
    private $similarityMethod;

    public function __construct(Database $db, $similarityMethod = 'cosine')
    {
        $this->db = $db;
        $this->similarityMethod = $similarityMethod;
    }

    public function searchSimilar(array $queryEmbedding, $topK = 5, $threshold = 0.0, $maxCandidates = 200)
    {
        $maxCandidates = intval($maxCandidates);
        $sql = "SELECT v.id, v.document_id, v.chunk_text, v.chunk_index, v.embedding, d.filename, d.original_name 
                FROM vectors v 
                INNER JOIN documents d ON v.document_id = d.id 
                WHERE d.is_active = 1
                ORDER BY RAND()
                LIMIT {$maxCandidates}";
        
        $vectors = $this->db->fetchAll($sql);
        
        if (empty($vectors)) {
            return [];
        }

        $results = [];
        
        foreach ($vectors as $vector) {
            $storedEmbedding = VectorMath::unserializeVector($vector['embedding']);
            
            if ($this->similarityMethod === 'cosine') {
                $score = VectorMath::cosineSimilarity($queryEmbedding, $storedEmbedding);
            } else {
                $distance = VectorMath::euclideanDistance($queryEmbedding, $storedEmbedding);
                $score = 1 / (1 + $distance);
            }

            if ($score >= $threshold) {
                $results[] = [
                    'id' => $vector['id'],
                    'document_id' => $vector['document_id'],
                    'chunk_text' => $vector['chunk_text'],
                    'chunk_index' => $vector['chunk_index'],
                    'filename' => $vector['filename'],
                    'original_name' => $vector['original_name'],
                    'score' => $score
                ];
            }
        }

        usort($results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($results, 0, $topK);
    }

    public function storeVector($documentId, $chunkText, $chunkIndex, array $embedding)
    {
        $binaryEmbedding = VectorMath::serializeVector($embedding);
        
        return $this->db->insert('vectors', [
            'document_id' => $documentId,
            'chunk_text' => $chunkText,
            'chunk_index' => $chunkIndex,
            'embedding' => $binaryEmbedding
        ]);
    }

    public function deleteVectorsByDocument($documentId)
    {
        return $this->db->delete('vectors', 'document_id = :document_id', [
            ':document_id' => $documentId
        ]);
    }

    public function countVectors()
    {
        $result = $this->db->fetchOne('SELECT COUNT(*) as total FROM vectors');
        return $result['total'] ?? 0;
    }
}
