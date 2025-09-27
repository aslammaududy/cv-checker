<?php

namespace App\Console\Commands;

use Gemini\Laravel\Facades\Gemini;
use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LoadJobDescCommand extends Command
{
    protected $signature = 'job-desc:load';

    protected $description = 'Load job description into Vector Database (Milvus)';

    public function handle(): void
    {
        $this->ensureCollectionExists();

        $jobdesc = $this->jobDescItems();

        $bar = $this->output->createProgressBar(count($jobdesc));
        $bar->start();

        foreach ($jobdesc as $i => $jd) {
            $response = Gemini::embeddingModel('gemini-embedding-001')
                ->embedContent($jd);

            Milvus::vector()->insert(
                collectionName: 'jobdesc',
                data: [
                    'vector' => $response->embedding->values,
                    'jobdesc_id' => $i + 1,
                    'description' => $jd,
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Done seeding job description to Milvus');

    }

    private function ensureCollectionExists(): void
    {
        try {
            $collections = Milvus::collections()->list()->json();
            $collectionNames = array_column($collections['data'] ?? [], 'name');

            if (!in_array('rubric', $collectionNames)) {
                Milvus::collections()->create(collectionName: 'jobdesc', dimension: 3072);
            }
        } catch (\Exception $e) {
            Log::error('Failed to ensure collection exists', ['error' => $e->getMessage()]);
        }
    }

    private function jobDescItems(): array
    {
        return [
            'backend technologies',
            'backend languages and frameworks (Node.js, Django, Rails)',
            'Database management (MySQL, PostgreSQL, MongoDB)',
            'RESTful APIs',
            'Security compliance',
            'Cloud technologies (AWS, Google Cloud, Azure)',
            'Server-side languages (Java, Python, Ruby, or JavaScript)',
            'Understanding of frontend technologies',
            'User authentication and authorization between multiple systems, servers, and environments',
            'Scalable application design principles',
            'Creating database schemas that represent and support business processes',
            'Implementing automated testing platforms and unit tests',
            'Familiarity with LLM APIs, embeddings, vector databases and prompt design best practices',
            'Agile methodology',
            'robust, clean, efficient code',
            'AI-powered systems',
            'orchestrate large language models (LLMs) and integrate into systems',
        ];
    }
}
