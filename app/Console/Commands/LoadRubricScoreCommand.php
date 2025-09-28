<?php

namespace App\Console\Commands;

use Gemini\Laravel\Facades\Gemini;
use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LoadRubricScoreCommand extends Command
{
    protected $signature = 'rubric:load';

    protected $description = 'Load Rubric Score into Vector Database (Milvus)';

    public function handle(): void
    {
        $this->ensureCollectionExists();

        $rubrics = $this->rubricItems();

        $bar = $this->output->createProgressBar(count($rubrics));
        $bar->start();

        foreach ($rubrics as $i => $r) {
            $text = "{$r['category']}: {$r['description']} (Weight {$r['weight']}%)";

            $response = Gemini::embeddingModel('gemini-embedding-001')
                ->embedContent($text);

            Milvus::vector()->insert(
                collectionName: 'rubric',
                data: [
                    'vector' => $response->embedding->values,
                    'rubric_id' => $i + 1,
                    'category' => $r['category'],
                    'description' => $r['description'],
                    'weight' => $r['weight'],
                    'group' => $r['group'],
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Done seeding rubrics to Milvus');
    }

    private function ensureCollectionExists(): void
    {
        try {
            $collections = Milvus::collections()->list()->json();
            $collectionNames = array_column($collections['data'] ?? [], 'name');

            if (!in_array('rubric', $collectionNames)) {
                Milvus::collections()->create(collectionName: 'rubric', dimension: 3072);
            }
        } catch (\Exception $e) {
            Log::error('Failed to ensure collection exists', ['error' => $e->getMessage()]);
        }
    }

    private function rubricItems(): array
    {
        return [
            // CV Match (untuk referensi retrieval/penilaian)
            ['category' => 'Technical Skills Match', 'description' => 'Alignment with backend, databases, APIs, cloud, and any AI/LLM exposure.', 'weight' => 0.4, 'group' => 'cv'],
            ['category' => 'Experience Level', 'description' => 'Years and complexity of projects delivered; track record and impact.', 'weight' => 0.25, 'group' => 'cv'],
            ['category' => 'Relevant Achievements', 'description' => 'Measurable outcomes like scaling, performance, adoption, reliability.', 'weight' => 0.2, 'group' => 'cv'],
            ['category' => 'Cultural / Collaboration Fit', 'description' => 'Communication, learning mindset, teamwork/leadership.', 'weight' => 0.15, 'group' => 'cv'],

            // Project Deliverables
            ['category' => 'Correctness (Prompt & Chaining)', 'description' => 'Implements prompt design, LLM chaining, and RAG context injection correctly.', 'weight' => 0.3, 'group' => 'project'],
            ['category' => 'Code Quality & Structure', 'description' => 'Clean, modular, reusable, tested code and sensible structure.', 'weight' => 0.25, 'group' => 'project'],
            ['category' => 'Resilience & Error Handling', 'description' => 'Handles long jobs, retries/backoff, timeouts, and LLM randomness.', 'weight' => 0.2, 'group' => 'project'],
            ['category' => 'Documentation & Explanation', 'description' => 'Clear README, setup, and trade-off explanations; testing notes.', 'weight' => 0.15, 'group' => 'project'],
            ['category' => 'Creativity / Bonus', 'description' => 'Useful extras beyond requirements (auth, deployment, dashboards, etc.).', 'weight' => 0.1, 'group' => 'project'],
        ];
    }
}
