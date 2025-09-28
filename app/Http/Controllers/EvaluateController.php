<?php

namespace App\Http\Controllers;

use App\Jobs\EvaluateCvProjectJob;
use App\Models\Evaluation;
use Illuminate\Http\Request;

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

        return response()->json([
            'id' => $evaluation->id,
            'status' => $evaluation->status,
            'result' => json_decode($evaluation->result),
        ]);
    }
}
