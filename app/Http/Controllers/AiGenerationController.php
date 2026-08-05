<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFormJob;
use App\Models\AiGeneration;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AiGenerationController extends Controller
{
    /**
     * Queue a generation and return its id for polling. Never blocks the
     * request on the LLM call.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|min:5|max:4000',
            'mode' => 'required|in:create,edit',
            'form_id' => 'required_if:mode,edit|nullable|integer|exists:forms,id',
        ]);

        $form = null;

        if ($validated['mode'] === 'edit') {
            $form = Form::findOrFail($validated['form_id']);
            Gate::authorize('update', $form);
        }

        $generation = AiGeneration::createForTenant($request->user(), [
            'form_id' => $form?->id,
            'mode' => $validated['mode'],
            'prompt' => $validated['prompt'],
            'status' => AiGeneration::STATUS_QUEUED,
        ]);

        GenerateFormJob::dispatch($generation->id);

        return response()->json(['id' => $generation->id], 201);
    }

    /** Polling endpoint for the builder's AI panel. */
    public function show(Request $request, AiGeneration $generation): JsonResponse
    {
        Gate::authorize('view', $generation);

        return response()->json([
            'id' => $generation->id,
            'status' => $generation->status,
            'mode' => $generation->mode,
            'result_schema' => $generation->status === AiGeneration::STATUS_DONE
                ? $generation->result_schema
                : null,
            'warnings' => $generation->warnings ?? [],
            'error' => $generation->error,
            'stats' => [
                'model' => $generation->model,
                'attempts' => $generation->attempts,
                'prompt_tokens' => $generation->prompt_tokens,
                'completion_tokens' => $generation->completion_tokens,
                'latency_ms' => $generation->latency_ms,
            ],
        ]);
    }
}
