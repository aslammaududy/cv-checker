<?php

namespace App\Http\Controllers;

use App\Models\Evaluation;
use App\Services\EvaluationService;
use Gemini\Laravel\Facades\Gemini;
use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class UploadController extends Controller
{
    /**
     * Upload CV and project files
     *
     * Accepts PDF files for both CV and project documents.
     * Files are stored and associated with the authenticated user.
     * Maximum file size is 1MB per file.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf|max:1024',
            'project' => 'required|file|mimes:pdf|max:1024',
        ]);

        $user_email = $request->user()->email;

        $request->file('cv')->storeAs('uploads/cv', "{$user_email}_cv.pdf");
        $request->file('project')->storeAs('uploads/projects', "{$user_email}_project.pdf");

        $evaluation = Evaluation::where('user_id', auth()->user()->id)->first();

        if (!$evaluation) {
            Evaluation::create([
                'user_id' => auth()->user()->id,
                'cv' => "uploads/cv/{$user_email}_cv.pdf",
                'project' => "uploads/projects/{$user_email}_project.pdf",
                'status' => "uploaded",
            ]);
        }

        return response()->json(['message' => 'Files uploaded successfully']);
    }


}
