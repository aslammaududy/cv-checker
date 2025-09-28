<?php

namespace App\Jobs;

use App\Models\Evaluation;
use App\Services\EvaluationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateCvProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3; // retry 3 times
    public int $backoff = 5; // wait for 5 seconds before retries

    public function __construct(public Evaluation $evaluation)
    {
    }

    public function handle(EvaluationService $service): void
    {
        try {
            $service->convertPDFToText($this->evaluation)->evaluate();
        } catch (\Exception $exception) {
            $this->fail($exception);
        }
    }
}
