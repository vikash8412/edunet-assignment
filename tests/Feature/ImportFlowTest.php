<?php

use App\Jobs\ParseImportJob;
use App\Models\Form;
use App\Models\Import;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('uploads a document, queues parsing, and reports status', function () {
    Storage::fake();
    Queue::fake();
    $user = User::factory()->create();

    $file = new UploadedFile(
        base_path('samples/job-application.docx'),
        'job-application.docx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        null,
        true,
    );

    $response = $this->actingAs($user)->post(route('imports.store'), ['file' => $file]);

    $response->assertCreated();
    Queue::assertPushed(ParseImportJob::class);

    $import = Import::sole();
    expect($import->kind)->toBe('docx')->and($import->status)->toBe('queued');
    Storage::assertExists($import->path);
});

it('rejects unsupported file types', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('imports.store'), [
            'file' => UploadedFile::fake()->create('malware.exe', 10),
        ])
        ->assertSessionHasErrors('file');
});

it('parses an uploaded docx end-to-end through the job', function () {
    config(['services.gemini.key' => null]); // deterministic only
    $user = User::factory()->create();

    $import = Import::createForTenant($user, [
        'original_name' => 'job-application.docx',
        'path' => 'imports/job-application.docx',
        'kind' => 'docx',
        'status' => Import::STATUS_QUEUED,
    ]);

    Storage::makeDirectory('imports');
    copy(base_path('samples/job-application.docx'), Storage::path($import->path));

    (new ParseImportJob($import->id))->handle(
        app(App\Services\Import\WordParser::class),
        app(App\Services\Import\ExcelParser::class),
        app(App\Services\Import\AiTypeResolver::class),
        app(App\Services\Schema\SchemaNormalizer::class),
        app(App\Services\Schema\SchemaValidator::class),
    );

    $import->refresh();

    expect($import->status)->toBe('ready')
        ->and($import->parsed_schema['title'])->toBe('Job Application Form')
        ->and($import->parsed_schema['sections'])->not->toBeEmpty();

    Storage::delete($import->path);
});

it('commits a ready import as a draft form with version source=import', function () {
    $user = User::factory()->create();

    $import = Import::createForTenant($user, [
        'original_name' => 'x.docx',
        'path' => 'imports/x.docx',
        'kind' => 'docx',
        'status' => Import::STATUS_READY,
        'parsed_schema' => baseSchema(),
    ]);

    $response = $this->actingAs($user)->post(
        route('imports.commit', $import),
        ['schema' => baseSchema(['title' => 'Corrected on mapping screen'])],
    );

    $form = Form::sole();

    $response->assertRedirect(route('forms.edit', ['form' => $form->id, 'step' => 'builder']));

    expect($form->title)->toBe('Corrected on mapping screen')
        ->and($form->status)->toBe('draft')
        ->and($form->versions->first()->source)->toBe('import')
        ->and($import->refresh()->status)->toBe('committed')
        ->and($import->form_id)->toBe($form->id);
});

it('refuses to commit someone else\'s import or a broken schema', function () {
    $owner = User::factory()->create();
    $import = Import::createForTenant($owner, [
        'original_name' => 'x.docx',
        'path' => 'imports/x.docx',
        'kind' => 'docx',
        'status' => Import::STATUS_READY,
        'parsed_schema' => baseSchema(),
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('imports.commit', $import), ['schema' => baseSchema()])
        ->assertForbidden();

    $broken = baseSchema();
    $broken['sections'][0]['fields'][0]['type'] = 'quantum';

    $this->actingAs($owner)
        ->post(route('imports.commit', $import), ['schema' => $broken])
        ->assertSessionHasErrors('schema');

    expect(Form::count())->toBe(0);
});
