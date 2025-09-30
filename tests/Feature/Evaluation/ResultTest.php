<?php

namespace Tests\Feature\Evaluation;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_result_not_found_returns_failed_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/result/9999');

        $response->assertOk()->assertJson([
            'status' => 'failed',
        ]);
    }

    public function test_result_processing_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/'.$user->email.'_cv.pdf',
            'project' => 'uploads/projects/'.$user->email.'_project.pdf',
            'status' => 'processing',
        ]);

        $response = $this->getJson('/api/result/'.$evaluation->id);

        $response->assertOk()->assertJson([
            'id' => $evaluation->id,
            'status' => 'processing',
        ]);
    }

    public function test_result_failed_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/'.$user->email.'_cv.pdf',
            'project' => 'uploads/projects/'.$user->email.'_project.pdf',
            'status' => 'failed',
        ]);

        $response = $this->getJson('/api/result/'.$evaluation->id);

        $response->assertOk()->assertJson([
            'id' => $evaluation->id,
            'status' => 'failed',
        ])->assertJsonStructure([
            'id', 'status', 'message'
        ]);
    }

    public function test_result_completed_returns_decoded_result(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $result = [
            'cv_match_rate' => 0.89,
            'project_score' => 7.9,
        ];

        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/'.$user->email.'_cv.pdf',
            'project' => 'uploads/projects/'.$user->email.'_project.pdf',
            'status' => 'completed',
            'result' => json_encode($result),
        ]);

        $response = $this->getJson('/api/result/'.$evaluation->id);

        $response->assertOk()
            ->assertJson([
                'id' => $evaluation->id,
                'status' => 'completed',
            ])
            ->assertJsonPath('result.cv_match_rate', 0.89)
            ->assertJsonPath('result.project_score', 7.9);
    }
}

