<?php

namespace Tests\Feature\Evaluation;

use App\Jobs\EvaluateCvProjectJob;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class EvaluateTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_queues_job_and_sets_status(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'jill@example.com']);
        Sanctum::actingAs($user);

        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/jill@example.com_cv.pdf',
            'project' => 'uploads/projects/jill@example.com_project.pdf',
            'status' => 'uploaded',
        ]);

        // Mock the EvaluationService to avoid PDF/AI work
        $mock = Mockery::mock(EvaluationService::class);
        $mock->shouldReceive('convertPDFToText')->once()->with(Mockery::on(function ($arg) use ($evaluation) {
            return $arg->id === $evaluation->id;
        }))->andReturn($mock);
        $this->instance(EvaluationService::class, $mock);

        $response = $this->postJson('/api/evaluate');

        $response->assertOk()->assertJson([
            'id' => $evaluation->id,
            'status' => 'queued',
        ]);

        Queue::assertPushed(EvaluateCvProjectJob::class, function ($job) use ($evaluation) {
            return $job->evaluation->id === $evaluation->id;
        });

        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'status' => 'queued',
        ]);
    }

    public function test_evaluate_returns_error_when_conversion_fails(): void
    {
        $user = User::factory()->create(['email' => 'jack@example.com']);
        Sanctum::actingAs($user);

        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/jack@example.com_cv.pdf',
            'project' => 'uploads/projects/jack@example.com_project.pdf',
            'status' => 'uploaded',
        ]);

        $mock = Mockery::mock(EvaluationService::class);
        $mock->shouldReceive('convertPDFToText')->once()->andThrow(new \Exception('Could not extract text from PDF (CV)'));
        $this->instance(EvaluationService::class, $mock);

        $response = $this->postJson('/api/evaluate');

        $response->assertOk()->assertJson([
            'error' => 'Could not extract text from PDF (CV)',
        ]);
    }
}

