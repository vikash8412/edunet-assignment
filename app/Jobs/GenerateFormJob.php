<?php

namespace App\Jobs;

use App\Models\AiGeneration;
use App\Services\Ai\FormGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateFormJob implements ShouldQueue
{
    use Queueable;

    /** The pipeline manages its own model-level retries. */
    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public readonly int $generationId)
    {
    }

    public function handle(FormGenerator $generator): void
    {
        $generation = AiGeneration::find($this->generationId);

        if (! $generation || $generation->status !== AiGeneration::STATUS_QUEUED) {
            return;
        }

        $generator->run($generation);
    }

    public function failed(?Throwable $exception): void
    {
        AiGeneration::where('id', $this->generationId)->update([
            'status' => AiGeneration::STATUS_FAILED,
            'error' => 'Job crashed: '.($exception?->getMessage() ?? 'unknown error'),
        ]);
    }
}
