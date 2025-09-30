<?php

namespace Tests\Unit\Jobs;

use App\Jobs\EvaluateCvProjectJob;
use App\Models\Evaluation;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EvaluateCvProjectJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_sets_processing_and_calls_service(): void
    {
        $user = User::factory()->create();
        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/'.$user->email.'_cv.pdf',
            'project' => 'uploads/projects/'.$user->email.'_project.pdf',
            'status' => 'uploaded',
        ]);

        $job = new EvaluateCvProjectJob($evaluation);

        $mock = Mockery::mock(EvaluationService::class);
        $mock->shouldReceive('convertPDFToText')->once()->with(Mockery::on(function ($arg) use ($evaluation) {
            return $arg->id === $evaluation->id;
        }))->andReturn($mock);
        $mock->shouldReceive('evaluate')->once()->andReturnNull();

        $job->handle($mock);

        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'status' => 'processing',
        ]);
    }

    public function test_failed_sets_failed_status(): void
    {
        $user = User::factory()->create();
        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/'.$user->email.'_cv.pdf',
            'project' => 'uploads/projects/'.$user->email.'_project.pdf',
            'status' => 'uploaded',
        ]);

        $job = new EvaluateCvProjectJob($evaluation);
        $job->failed(new \Exception('boom'));

        $this->assertDatabaseHas('evaluations', [
            'id' => $evaluation->id,
            'status' => 'failed',
        ]);
    }
}

