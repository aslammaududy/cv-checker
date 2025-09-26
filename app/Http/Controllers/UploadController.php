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
    public function __invoke(Request $request, EvaluationService $service)
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf|max:2048', // 2MB max
        ]);

        try {
            $service->convertPDFToText($request)->evaluate();

            return response()->json(['message' => 'CV processed successfully']);

        } catch (\Exception $e) {
            Log::error('CV upload failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to process CV', 'error' => $e->getMessage()], 500);
        }
    }


}
