<?php

namespace App\Utils;

class TextProcessor
{
    public static function cleanText($text)
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text;
    }

    public static function chunkText($text, $chunkSize = 500, $overlap = 50)
    {
        $text = self::cleanText($text);
        $words = explode(' ', $text);
        $chunks = [];
        $currentChunk = [];
        $wordCount = 0;

        foreach ($words as $word) {
            $currentChunk[] = $word;
            $wordCount++;

            if ($wordCount >= $chunkSize) {
                $chunks[] = implode(' ', $currentChunk);
                $currentChunk = array_slice($currentChunk, -$overlap);
                $wordCount = count($currentChunk);
            }
        }

        if (!empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }

    public static function extractTextFromPDF($filepath)
    {
        if (!class_exists('\Smalot\PdfParser\Parser')) {
            throw new \RuntimeException('PDF Parser not installed');
        }

        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filepath);
        $text = $pdf->getText();
        
        return self::cleanText($text);
    }

    public static function extractTextFromDOCX($filepath)
    {
        if (!class_exists('\PhpOffice\PhpWord\IOFactory')) {
            throw new \RuntimeException('PHPWord not installed');
        }

        $phpWord = \PhpOffice\PhpWord\IOFactory::load($filepath);
        $text = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (method_exists($element, 'getText')) {
                    $text .= $element->getText() . ' ';
                } elseif (method_exists($element, 'getElements')) {
                    foreach ($element->getElements() as $childElement) {
                        if (method_exists($childElement, 'getText')) {
                            $text .= $childElement->getText() . ' ';
                        }
                    }
                }
            }
        }

        return self::cleanText($text);
    }

    public static function extractTextFromTXT($filepath)
    {
        $text = file_get_contents($filepath);
        return self::cleanText($text);
    }

    public static function extractText($filepath, $fileType)
    {
        $fileType = strtolower($fileType);

        if ($fileType === 'pdf') {
            return self::extractTextFromPDF($filepath);
        } elseif ($fileType === 'docx') {
            return self::extractTextFromDOCX($filepath);
        } elseif ($fileType === 'txt') {
            return self::extractTextFromTXT($filepath);
        }

        throw new \InvalidArgumentException('Unsupported file type: ' . $fileType);
    }
}
