<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;
use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class EvaluationService
{
    private string $cvText;

    public function __construct()
    {
    }

    public function convertPDFToText(Request $request): static
    {
        $cvFile = $request->file('cv');
        $this->cvText = Pdf::getText($cvFile->getPathname());

        if (empty(trim($this->cvText))) {
            Log::error('Could not extract text from PDF', ['file' => $cvFile->getClientOriginalName()]);
            throw new \Exception("Could not extract text from PDF");
        }

        return $this;
    }

    public function evaluate()
    {
        $this->ensureCollectionExists();

        $cvChunks = $this->chunkText($this->cvText);

        $embeddings = [];

        foreach ($cvChunks as $index => $chunk) {
            if (empty(trim($chunk))) continue;

            $response = Gemini::embeddingModel('gemini-embedding-001')
                ->embedContent($chunk);

            $embeddings[] = [
                'vector' => $response->embedding->values,
                'content' => $chunk
            ];
        }

        // Batch insert all vectors
        if (!empty($embeddings)) {
            Milvus::vector()->insert(
                collectionName: 'cv',
                data: $embeddings
            );
        }
    }

    private function chunkText(string $text, int $chunkSize = 800): array
    {
        // Simple text chunking - could be improved with sentence boundaries
        $text = trim(preg_replace('/\s+/', ' ', $text)); // Normalize whitespace
        return array_filter(str_split($text, $chunkSize), fn($chunk) => !empty(trim($chunk)));
    }

    private function ensureCollectionExists(): void
    {
        try {
            // Check if collection exists first
            $collections = Milvus::collections()->list()->json();
            $collectionNames = array_column($collections['data'] ?? [], 'name');

            if (!in_array('cv', $collectionNames)) {
                Milvus::collections()->create(collectionName: 'cv', dimension: 3072);
            }
        } catch (\Exception $e) {
            Log::error('Failed to ensure collection exists', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
