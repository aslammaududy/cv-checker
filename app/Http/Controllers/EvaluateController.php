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

        return response()->json([
            'id' => $evaluation->id,
            'status' => 'queued',
        ]);
    }
}
