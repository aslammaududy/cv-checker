<?php

namespace Tests\Feature\Upload;

use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UploadFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_requires_authentication(): void
    {
        $response = $this->postJson('/api/upload', []);
        $response->assertStatus(401);
    }

    public function test_upload_success_and_creates_evaluation_if_missing(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'email' => 'uploader@example.com',
        ]);
        Sanctum::actingAs($user);

        $cv = UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf');
        $project = UploadedFile::fake()->create('project.pdf', 60, 'application/pdf');

        $response = $this->postJson('/api/upload', [
            'cv' => $cv,
            'project' => $project,
        ]);

        $response->assertOk()->assertJson([
            'message' => 'Files uploaded successfully',
        ]);

        // Files stored with user email in the name
        Storage::disk('local')->assertExists('uploads/cv/uploader@example.com_cv.pdf');
        Storage::disk('local')->assertExists('uploads/projects/uploader@example.com_project.pdf');

        $this->assertDatabaseHas('evaluations', [
            'user_id' => $user->id,
            'cv' => 'uploads/cv/uploader@example.com_cv.pdf',
            'project' => 'uploads/projects/uploader@example.com_project.pdf',
            'status' => 'uploaded',
        ]);
    }
}

