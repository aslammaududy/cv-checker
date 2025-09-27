<?php

namespace App\Http\Controllers;

use App\Jobs\EvaluateCvProjectJob;
use App\Models\Evaluation;

class EvaluateController extends Controller
{
    public function evaluate()
    {
        $evaluation = Evaluation::where('user_id', request()->user()->id)->firstOrFail();

        EvaluateCvProjectJob::dispatch($evaluation);

        $evaluation->status = 'queued';
        $evaluation->save();

        return response()->json([
            'id' => $evaluation->id,
            'status' => 'queued',
        ]);
    }

    public function result()
    {
        $evaluation = Evaluation::where('user_id', request()->user()->id)->firstOrFail();

        if ($evaluation->status == 'processing') {
            return response()->json([
                'id' => $evaluation->id,
                'status' => $evaluation->status,
            ]);
        }

        return response()->json([
            'id' => $evaluation->id,
            'status' => $evaluation->status,
        ]);
    }
}
