<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

class DocumentTextExtractor
{
    /**
     * Extract plain text from a Document (PDF via smalot/pdfparser, DOCX via ZipArchive).
     * Limit to the first ~30,000 characters.
     * Return '' gracefully on failure (log a warning).
     */
    public function extract(Document $document): string
    {
        try {
            $filePath = $document->file_path;
            if (! Storage::disk('public')->exists($filePath)) {
                Log::warning('DocumentTextExtractor: File does not exist on public disk', [
                    'document_id' => $document->id,
                    'file_path' => $filePath,
                ]);

                return '';
            }

            $fullPath = Storage::disk('public')->path($filePath);
            $mimeType = strtolower($document->mime_type ?? '');
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            $text = '';

            if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
                $text = $this->extractFromPdf($fullPath);
            } elseif ($extension === 'docx' || str_contains($mimeType, 'wordprocessingml')) {
                $text = $this->extractFromDocx($fullPath);
            } else {
                Log::warning('DocumentTextExtractor: Unsupported document format', [
                    'document_id' => $document->id,
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                ]);

                return '';
            }

            // Normalise whitespace and limit to first ~30,000 characters
            $trimmed = trim(preg_replace('/[^\S\r\n]+/', ' ', $text));

            return mb_substr($trimmed, 0, 30000);
        } catch (Throwable $e) {
            Log::warning('DocumentTextExtractor: Extraction failed: '.$e->getMessage(), [
                'document_id' => $document->id,
                'exception' => $e,
            ]);

            return '';
        }
    }

    protected function extractFromPdf(string $fullPath): string
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($fullPath);

        return $pdf->getText();
    }

    protected function extractFromDocx(string $fullPath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($fullPath) !== true) {
            Log::warning("DocumentTextExtractor: Unable to open DOCX zip: {$fullPath}");

            return '';
        }

        $xmlIndex = $zip->locateName('word/document.xml');
        if ($xmlIndex === false) {
            $zip->close();
            Log::warning("DocumentTextExtractor: word/document.xml not found in DOCX: {$fullPath}");

            return '';
        }

        $xmlContent = $zip->getFromIndex($xmlIndex);
        $zip->close();

        if ($xmlContent === false) {
            return '';
        }

        // Strip XML tags and decode entities
        $text = strip_tags($xmlContent);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
