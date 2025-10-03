<?php

namespace App\Http\Controllers;

use App\Jobs\EvaluateCvProjectJob;
use App\Models\Evaluation;
use App\Services\EvaluationService;
use Illuminate\Http\Request;

class EvaluateController extends Controller
{
    /**
     * Start CV and project evaluation
     *
     * Initiates the evaluation process for the user's uploaded CV and project files.
     * The evaluation is processed asynchronously in a queue.
     * Returns an evaluation ID and status for tracking.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function evaluate(EvaluationService $service)
    {
        $evaluation = Evaluation::where('user_id', request()->user()->id)->firstOrFail();

        try {
            $service->convertPDFToText($evaluation);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }

        EvaluateCvProjectJob::dispatch($evaluation);

        $evaluation->status = 'queued';
        $evaluation->save();

        return response()->json([
            'id' => $evaluation->id,
            'status' => 'queued',
        ]);
    }

    /**
     * Get evaluation results
     *
     * Retrieves the evaluation results for a specific evaluation ID.
     * Returns the evaluation status and results if processing is complete.
     * Possible statuses: queued, processing, complete, or failed.
     *
     * @param int $id The evaluation ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function result(int $id)
    {
        $evaluation = Evaluation::where('id', $id)->first();

        if (is_null($evaluation)) {
            return response()->json([
                'status' => 'failed',
                'messsage' => 'Not found',
            ]);
        }

        if ($evaluation->status == 'processing') {
            return response()->json([
                'id' => $evaluation->id,
                'status' => $evaluation->status,
            ]);
        }

        if ($evaluation->status == 'failed') {
            return response()->json([
                'id' => $evaluation->id,
                'status' => $evaluation->status,
                'message' => "Failed to evaluate the files. Please re run the evaluation."
            ]);
        }

        return response()->json([
            'id' => $evaluation->id,
            'status' => $evaluation->status,
            'result' => json_decode($evaluation->result),
        ]);
    }
}
