<?php

namespace App\Http\Controllers;

use App\Services\EvaluationService;
use Gemini\Laravel\Facades\Gemini;
use HelgeSverre\Milvus\Facades\Milvus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\PdfToText\Pdf;

class UploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'cv' => 'required|file|mimes:pdf|max:1024',
            'project' => 'required|file|mimes:pdf|max:1024',
        ]);

        $request->file('cv')->storeAs('uploads/cv', "{$request->email}_cv.pdf");
        $request->file('project')->storeAs('uploads/projects', "{$request->email}_project.pdf");

        return response()->json(['message' => 'Files uploaded successfully']);
    }


}
