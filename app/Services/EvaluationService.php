<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\User;
use Gemini\Data\GenerationConfig;
use Gemini\Enums\ResponseMimeType;
use Gemini\Laravel\Facades\Gemini;
use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class EvaluationService
{
    private string $cvText;
    private string $projectText;
    private User $user;

    private Evaluation $evaluation;

    public function convertPDFToText(Evaluation $evaluation): static
    {
        $this->cvText = Pdf::getText(storage_path("app/private/{$evaluation->cv}"));
        $this->projectText = Pdf::getText(storage_path("app/private/{$evaluation->project}"));

        $this->evaluation = $evaluation;
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

        $this->embedCvAndProject();

        $jobdesc = $this->findJobDescFromCV();

        $rubricCV = Milvus::vector()->query(
            collectionName: 'rubric',
            filter: "group == 'cv'",
            outputFields: ["category", "description", "weight", "vector", "guide"]
        );

        $rubricProject = Milvus::vector()->query(
            collectionName: 'rubric',
            filter: "group == 'project'",
            outputFields: ["category", "description", "weight", "vector", "guide"]
        );

        $rubricDataForCV = $rubricCV->array('data');
        $rubricDataForProject = $rubricProject->array('data');

        $filteredCv = $this->filterCVBasedOnRubricScore($rubricDataForCV);
        $filteredProject = $this->filterProjectBasedOnRubricScore($rubricDataForProject);

        $promptCv = $this->generatePromptForCVScoring($rubricDataForCV, $jobdesc, $filteredCv);
        $promptProject = $this->generatePromptForProjectScoring($rubricDataForProject, $filteredProject);

        $cvScore = $this->scoringCV($promptCv);
        $projectScore = $this->scoringProject($promptProject);
        $refinedProjectScore = $this->refineProjectScore($projectScore->json(), $rubricDataForProject);

        $result = $this->generateResult($cvScore->json(), $refinedProjectScore->json());

        $this->evaluation->result = json_encode($result->json());
        $this->evaluation->status = 'completed';
        $this->evaluation->save();
    }

    private function embedCvAndProject()
    {
        $user_cv = Milvus::vector()->query(
            collectionName: 'cv',
            filter: "user_id == {$this->user->id}",
        );

        $user_project = Milvus::vector()->query(
            collectionName: 'project',
            filter: "user_id == {$this->user->id}",
        );

        if (!empty($user_cv->array('data'))) {
            foreach ($user_cv->array('data') as $key => $value) {
                Milvus::vector()->delete(
                    id: $value['id'],
                    collectionName: 'cv',
                );
            }
        }

        if (!empty($user_project->array('data'))) {
            foreach ($user_project->array('data') as $key => $value) {
                Milvus::vector()->delete(
                    id: $value['id'],
                    collectionName: 'project',
                );
            }
        }

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
                'user_id' => $this->user->id,
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
                'user_id' => $this->user->id,
            ];
        }

        $this->vectorize($cvEmbeddings, $projectEmbeddings);
    }

    private function vectorize($cvEmbed, $projectEmbed)
    {
        // Batch insert all vectors
        if (!empty($cvEmbed)) {
            Milvus::vector()->insert(
                collectionName: 'cv',
                data: $cvEmbed
            );
        }

        if (!empty($projectEmbed)) {
            Milvus::vector()->insert(
                collectionName: 'project',
                data: $projectEmbed
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

    private function findJobDescFromCV(): array
    {
        $jobdesc = [];

        $response = Milvus::vector()->query(
            collectionName: 'cv',
            filter: "user_id == {$this->user->id}",
            outputFields: ["user_id", "content", "vector"]
        );

        $cvVector = array_column($response->array('data'), 'vector');

        foreach ($cvVector as $vector) {
            $resjd = Milvus::vector()->search(
                collectionName: 'jobdesc',
                vector: $vector,
                outputFields: ["description"]
            );

            //get the top 3. because milvus use L2 as default index
            $jobdesc[] = array_column(array_slice($resjd->array('data'), 0, 3), 'description');
        }

        return $jobdesc;
    }

    private function filterCVBasedOnRubricScore(array $rubricData): array
    {
        $filteredCv = [];
        foreach ($rubricData as $data) {
            $cv = Milvus::vector()->search(
                collectionName: 'cv',
                vector: $data['vector'],
                outputFields: ["user_id", "content", "vector"]
            );

            $filteredCv[$data['category']] = array_slice($cv->array('data'), 0, 3);
        }

        return $filteredCv;
    }

    private function generatePromptForCVScoring(array $rubricData, array $jobdesc, array $filteredCv): array
    {
        return [
            'jd_context' => $jobdesc,
            'params' => array_map(function ($item) use ($filteredCv) {
                return [
                    'parameter' => $item['category'],
                    'weight' => $item['weight'],
                    'rubric_desc' => $item['description'],
                    'guide' => $item['guide'],
                    'cv_snippets' => array_map(fn($i) => $i['content'], $filteredCv[$item['category']]),
                    'scale' => 'Score 1–5 using guide times weight'
                ];
            }, $rubricData)
        ];
    }

    private function generatePromptForProjectScoring(mixed $rubricData, array $filteredProject): array
    {
        return [
            'params' => array_map(function ($item) use ($filteredProject) {
                return [
                    'parameter' => $item['category'],
                    'weight' => $item['weight'],
                    'rubric_desc' => $item['description'],
                    'guide' => $item['guide'],
                    'project_snippets' => array_map(fn($i) => $i['content'], $filteredProject[$item['category']]),
                    'scale' => 'Score 1–5 using guide times weight'
                ];
            }, $rubricData)
        ];
    }

    private function scoringCV(array $prompt): \Gemini\Responses\GenerativeModel\GenerateContentResponse
    {
        return Gemini::generativeModel('gemini-2.5-flash')
            ->withGenerationConfig(
                new GenerationConfig(
                    temperature: 0.1,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON
                )
            )
            ->generateContent(
                "From the provided json please generate the result as the following json output:
                {
                    'cv_match_rate': {rate},
                    'cv_feedback': {
                    'Technical Skills Match' : {skill},
                    'Experience Level' : {experience},
                    'Relevant Achievements' : {achievements}
                    'Cultural / Collaboration Fit': {collaboration}
                    }
                }
                replace the curly braces with your answer. for the scoring use the following formula:
                cv_match_rate: your generated score times 0.2 and round it to 2 floating point.
                For the cv_feedback replace {skill}, {experience}, {achievements}, {collaboration} with short and concise sentences.
                " .
                json_encode($prompt)
            );
    }

    private function scoringProject(array $prompt): \Gemini\Responses\GenerativeModel\GenerateContentResponse
    {
        return Gemini::generativeModel('gemini-2.5-flash')
            ->withGenerationConfig(
                new GenerationConfig(
                    temperature: 0.1,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON
                )
            )
            ->generateContent(
                "From the provided json please generate the result as the following json output:
                {
                    'project_match_rate': {rate},
                    'project_feedback': {feedback}
                }
                replace the curly braces with your answer.
                For the project_feedback replace {feedback} with short and concise sentences.
                " .
                json_encode($prompt)
            );
    }

    private function filterProjectBasedOnRubricScore(mixed $rubricData): array
    {
        $filteredProject = [];
        foreach ($rubricData as $data) {
            $project = Milvus::vector()->search(
                collectionName: 'project',
                vector: $data['vector'],
                outputFields: ["user_id", "content", "vector"]
            );

            $filteredProject[$data['category']] = array_slice($project->array('data'), 0, 3);
        }

        return $filteredProject;
    }

    private function refineProjectScore(mixed $json, array $rubricData): \Gemini\Responses\GenerativeModel\GenerateContentResponse
    {
        $refineScorePrompt = [
            'params' => array_map(function ($item) use ($json) {
                return [
                    'parameter' => $item['category'],
                    'weight' => $item['weight'],
                    'rubric_desc' => $item['description'],
                    'guide' => $item['guide'],
                    'initial_score' => $json->project_match_rate,
                ];
            }, $rubricData)
        ];

        return Gemini::generativeModel('gemini-2.5-flash')
            ->withGenerationConfig(
                new GenerationConfig(
                    temperature: 0.1,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON
                )
            )
            ->generateContent(
                "From the provided json please generate the result as the following json output:
                {
                    'project_match_rate': {rate},
                    'project_feedback': {feedback}
                }
                refine the initial score based on parameter, rubric_desc and guide also replace the curly braces with proper value
                " .
                json_encode($refineScorePrompt)
            );
    }

    private function generateResult(mixed $jsonScore, mixed $jsonProject)
    {
        return Gemini::generativeModel('gemini-2.5-flash')
            ->withGenerationConfig(
                new GenerationConfig(
                    temperature: 0.1,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON
                )
            )
            ->generateContent(
                'From the provided json files please combine them as the following json output:
                {
                    "cv_match_rate": {cv_match_rate},
                    "cv_feedback": {
                        "Technical Skills Match" : {Technical Skills Match},
                        "Experience Level": {Experience Level},
                        "Relevant Achievements" : {Relevant Achievements}
                        "Cultural / Collaboration Fit": {Cultural / Collaboration Fit}
                    },
                    "project_score": {project_score},
                    "project_feedback": "{project_feedback}",
                    "overall_summary": "{overall_summary}",
                }
                Overall Summaryshould return 3–5 sentences (strengths, gaps, recommendations).
                ' .
                json_encode($jsonScore) . ' ' . json_encode($jsonProject)
            );
    }
}
