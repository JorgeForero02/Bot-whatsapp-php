<?php

namespace App\Utils;

class VectorMath
{
    public static function cosineSimilarity(array $vec1, array $vec2)
    {
        if (count($vec1) !== count($vec2)) {
            throw new \InvalidArgumentException('Vectors must have the same dimension');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] * $vec1[$i];
            $magnitude2 += $vec2[$i] * $vec2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    public static function euclideanDistance(array $vec1, array $vec2)
    {
        if (count($vec1) !== count($vec2)) {
            throw new \InvalidArgumentException('Vectors must have the same dimension');
        }

        $sum = 0;
        for ($i = 0; $i < count($vec1); $i++) {
            $diff = $vec1[$i] - $vec2[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    public static function normalizeVector(array $vector)
    {
        $magnitude = 0;
        foreach ($vector as $value) {
            $magnitude += $value * $value;
        }
        
        $magnitude = sqrt($magnitude);
        
        if ($magnitude == 0) {
            return $vector;
        }

        return array_map(function($value) use ($magnitude) {
            return $value / $magnitude;
        }, $vector);
    }

    public static function serializeVector(array $vector)
    {
        return pack('f*', ...$vector);
    }

    public static function unserializeVector($binaryData)
    {
        return array_values(unpack('f*', $binaryData));
    }
}
