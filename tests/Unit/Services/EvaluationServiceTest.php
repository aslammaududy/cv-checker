<?php

namespace Tests\Unit\Services;

use App\Models\Evaluation;
use App\Models\User;
use App\Services\EvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EvaluationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_convert_pdf_to_text_throws_when_cv_text_empty(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);
        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/alice@example.com_cv.pdf',
            'project' => 'uploads/projects/alice@example.com_project.pdf',
            'status' => 'uploaded',
        ]);

        // Alias mock for static calls
        $pdf = Mockery::mock('alias:Spatie\\PdfToText\\Pdf');
        $pdf->shouldReceive('getText')->twice()->andReturn('');

        $service = new EvaluationService();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not extract text from PDF (CV)');
        $service->convertPDFToText($evaluation);
    }

    public function test_convert_pdf_to_text_succeeds_when_text_present(): void
    {
        $user = User::factory()->create(['email' => 'bob@example.com']);
        $evaluation = Evaluation::create([
            'user_id' => $user->id,
            'cv' => 'uploads/cv/bob@example.com_cv.pdf',
            'project' => 'uploads/projects/bob@example.com_project.pdf',
            'status' => 'uploaded',
        ]);

        $pdf = Mockery::mock('alias:Spatie\\PdfToText\\Pdf');
        // First call for CV, second for Project
        $pdf->shouldReceive('getText')->once()->andReturn('cv text');
        $pdf->shouldReceive('getText')->once()->andReturn('project text');

        $service = new EvaluationService();
        $result = $service->convertPDFToText($evaluation);

        $this->assertInstanceOf(EvaluationService::class, $result);
    }
}

