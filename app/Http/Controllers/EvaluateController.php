<?php

namespace App\Http\Controllers;

use App\Jobs\EvaluateCvProjectJob;
use App\Models\Evaluation;

class EvaluateController extends Controller
{
    public function evaluate()
    {
        $evaluation = Evaluation::first();
        EvaluateCvProjectJob::dispatch($evaluation);

        return response()->json([
            'id' => 1,
            'status' => 'queued',
        ]);
    }
}
