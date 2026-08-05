<?php

use App\Jobs\GenerateFormJob;
use App\Models\AiGeneration;
use App\Models\Form;
use App\Models\User;
use App\Services\Ai\FormGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.gemini.key' => 'test-key']);
});

function geminiResponse(string $text, int $promptTokens = 100, int $completionTokens = 200): array
{
    return [
        'candidates' => [
            ['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP'],
        ],
        'usageMetadata' => [
            'promptTokenCount' => $promptTokens,
            'candidatesTokenCount' => $completionTokens,
        ],
    ];
}

function aiSchemaJson(): string
{
    return json_encode([
        'title' => 'Internship Application',
        'description' => 'Apply for our internship.',
        'settings' => ['multi_step' => false],
        'sections' => [[
            'id' => 'sec_ai000001',
            'title' => 'Basics',
            'fields' => [
                [
                    'id' => 'fld_ai000001',
                    'type' => 'text',
                    'key' => 'full_name',
                    'label' => 'Full name',
                    'required' => true,
                ],
                [
                    'id' => 'fld_ai000002',
                    'type' => 'email',
                    'key' => 'email',
                    'label' => 'Email',
                    'required' => true,
                ],
            ],
        ]],
    ]);
}

function runGeneration(string $mode = 'create', ?Form $form = null): AiGeneration
{
    $user = $form?->user ?? User::factory()->create();

    $generation = $user->aiGenerations()->create([
        'form_id' => $form?->id,
        'mode' => $mode,
        'prompt' => 'internship application with education history',
        'status' => AiGeneration::STATUS_QUEUED,
    ]);

    app(FormGenerator::class)->run($generation);

    return $generation->refresh();
}

it('turns a clean JSON reply into a validated schema with stats', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiResponse(aiSchemaJson()))]);

    $generation = runGeneration();

    expect($generation->status)->toBe('done')
        ->and($generation->result_schema['title'])->toBe('Internship Application')
        ->and($generation->attempts)->toBe(1)
        ->and($generation->prompt_tokens)->toBe(100)
        ->and($generation->completion_tokens)->toBe(200)
        ->and($generation->latency_ms)->toBeGreaterThanOrEqual(0)
        ->and($generation->model)->toBe('gemini-2.0-flash');
});

it('repairs fenced and prose-wrapped JSON', function () {
    $wrapped = "Here is your form:\n```json\n".aiSchemaJson()."\n```\nEnjoy!";
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiResponse($wrapped))]);

    $generation = runGeneration();

    expect($generation->status)->toBe('done');
});

it('coerces hallucinated field types via the alias map and reports warnings', function () {
    $schema = json_decode(aiSchemaJson(), true);
    $schema['sections'][0]['fields'][0]['type'] = 'tel';
    $schema['sections'][0]['fields'][] = [
        'id' => 'fld_ai000003', 'type' => 'hologram', 'key' => 'weird', 'label' => 'Weird',
    ];

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiResponse(json_encode($schema)))]);

    $generation = runGeneration();

    $fields = collect($generation->result_schema['sections'][0]['fields']);

    expect($generation->status)->toBe('done')
        ->and($fields->firstWhere('key', 'full_name')['type'])->toBe('phone')
        ->and($fields->firstWhere('key', 'weird')['type'])->toBe('text')
        ->and(implode(' ', $generation->warnings))->toContain('hologram');
});

it('feeds validator errors back and succeeds on retry', function () {
    // First reply is unparseable even after repair; second is valid.
    Http::fakeSequence('generativelanguage.googleapis.com/*')
        ->push(geminiResponse('this is not json at all'))
        ->push(geminiResponse(aiSchemaJson()));

    $generation = runGeneration();

    expect($generation->status)->toBe('done')
        ->and($generation->attempts)->toBe(2)
        ->and($generation->prompt_tokens)->toBe(200); // summed across attempts
});

it('fails cleanly after exhausting retries and never persists a broken schema', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiResponse('{{{{'))]);

    $generation = runGeneration();

    expect($generation->status)->toBe('failed')
        ->and($generation->attempts)->toBe(3)
        ->and($generation->result_schema)->toBeNull()
        ->and($generation->error)->toContain('Gave up after 3 attempts');
});

it('fails fast on transport errors without retrying', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(
            ['error' => ['message' => 'Resource has been exhausted']],
            429,
        ),
    ]);

    $generation = runGeneration();

    expect($generation->status)->toBe('failed')
        ->and($generation->attempts)->toBe(1)
        ->and($generation->error)->toContain('Resource has been exhausted');
});

it('includes the current schema when editing an existing form', function () {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(geminiResponse(aiSchemaJson()))]);

    $form = Form::factory()->create();
    runGeneration('edit', $form);

    Http::assertSent(function ($request) use ($form) {
        $body = $request->body();

        return str_contains($body, 'full_name') // current schema is embedded
            && str_contains($body, 'FULL updated schema');
    });
});

it('queues the job and returns an id from the endpoint', function () {
    Queue::fake();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('ai.generations.store'), [
        'prompt' => 'a contact form with name and message',
        'mode' => 'create',
    ]);

    $response->assertCreated();
    Queue::assertPushed(GenerateFormJob::class);

    expect(AiGeneration::sole()->status)->toBe('queued');
});

it('rejects edit requests for forms the user does not own', function () {
    Queue::fake();
    $stranger = User::factory()->create();
    $form = Form::factory()->create();

    $this->actingAs($stranger)->postJson(route('ai.generations.store'), [
        'prompt' => 'make everything required',
        'mode' => 'edit',
        'form_id' => $form->id,
    ])->assertForbidden();

    Queue::assertNothingPushed();
});

it('hides generation status from other users', function () {
    $generation = User::factory()->create()->aiGenerations()->create([
        'mode' => 'create',
        'prompt' => 'anything at all',
        'status' => AiGeneration::STATUS_QUEUED,
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('ai.generations.show', $generation))
        ->assertForbidden();

    $this->actingAs($generation->user)
        ->getJson(route('ai.generations.show', $generation))
        ->assertOk()
        ->assertJsonPath('status', 'queued');
});
