<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\User;
use Gemini\Laravel\Facades\Gemini;
use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class EvaluationService
{
    private string $cvText;
    private string $projectText;
    private User $user;

    public function __construct()
    {
    }

    public function convertPDFToText(Evaluation $evaluation): static
    {
        $this->cvText = Pdf::getText(storage_path("app/private/{$evaluation->cv}"));
        $this->projectText = Pdf::getText(storage_path("app/private/{$evaluation->project}"));

        $this->user = $evaluation->user;

        if (empty(trim($this->cvText))) {
            Log::error('Could not extract text from PDF', ['file' => "{$this->user->email}_cv.pdf"]);
            throw new \Exception("Could not extract text from PDF (CV)");
        }

        if (empty(trim($this->projectText))) {
            Log::error('Could not extract text from PDF', ['file' => "{$this->user->email}_project.pdf"]);
            throw new \Exception("Could not extract text from PDF (Project)");
        }

        return $this;
    }

    public function evaluate()
    {
        $this->ensureCollectionExists();

        $cvChunks = $this->chunkText($this->cvText);
        $projectChunks = $this->chunkText($this->projectText);

        $cvEmbeddings = [];

        foreach ($cvChunks as $index => $chunk) {
            if (empty(trim($chunk))) continue;

            $response = Gemini::embeddingModel('gemini-embedding-001')
                ->embedContent($chunk);

            $cvEmbeddings[] = [
                'vector' => $response->embedding->values,
                'content' => $chunk,
                'user_id' => $this->user->email,
            ];
        }

        $projectEmbeddings = [];

        foreach ($projectChunks as $index => $chunk) {
            if (empty(trim($chunk))) continue;

            $response = Gemini::embeddingModel('gemini-embedding-001')
                ->embedContent($chunk);

            $projectEmbeddings[] = [
                'vector' => $response->embedding->values,
                'content' => $chunk,
                'user_id' => $this->user->email,
            ];
        }

        // Batch insert all vectors
        if (!empty($cvEmbeddings)) {
            Milvus::vector()->insert(
                collectionName: 'cv',
                data: $cvEmbeddings
            );
        }

        if (!empty($projectEmbeddings)) {
            Milvus::vector()->insert(
                collectionName: 'project',
                data: $projectEmbeddings
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

            if (!in_array('project', $collectionNames)) {
                Milvus::collections()->create(collectionName: 'project', dimension: 3072);
            }
        } catch (\Exception $e) {
            Log::error('Failed to ensure collection exists', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
