<?php

use App\Models\Form;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

function publishedForm(array $schemaOverrides = []): Form
{
    return Form::factory()->create(
        $schemaOverrides ? ['schema' => baseSchema($schemaOverrides)] : [],
    );
}

function fillPayload(array $values): array
{
    return array_merge($values, [
        // Token minted 10 minutes ago: passes the minimum-fill-time check.
        '_fill_token' => Crypt::encryptString((string) now()->subMinutes(10)->timestamp),
        '_hp_website' => '',
    ]);
}

it('renders a published form and logs a view event once per session', function () {
    $form = publishedForm();

    $this->get('/f/'.$form->public_id)->assertOk()->assertInertia(
        fn ($page) => $page->component('Public/Fill')->where('preview', false),
    );
    $this->get('/f/'.$form->public_id)->assertOk();

    expect($form->events()->where('type', 'view')->count())->toBe(1);
});

it('hides drafts from the public but previews them for the owner', function () {
    $form = Form::factory()->draft()->create();

    $this->get('/f/'.$form->public_id)->assertNotFound();

    $this->actingAs($form->user)
        ->get('/f/'.$form->public_id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('preview', true));
});

it('shows a closed notice for archived forms', function () {
    $form = Form::factory()->create(['status' => 'archived']);

    $this->get('/f/'.$form->public_id)->assertOk()->assertInertia(
        fn ($page) => $page->component('Public/Closed'),
    );
});

it('accepts a valid submission and stores searchable data', function () {
    $form = publishedForm();

    $response = $this->post('/f/'.$form->public_id, fillPayload([
        'full_name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]));

    $response->assertRedirect()->assertSessionHas('fill_success', true);

    $submission = $form->submissions()->sole();

    expect($submission->data['full_name'])->toBe('Ada Lovelace')
        ->and($submission->search_text)->toContain('Ada Lovelace')
        ->and($submission->form_version_id)->not->toBeNull()
        ->and($form->events()->where('type', 'submit')->count())->toBe(1);
});

it('enforces schema validation server-side', function () {
    $form = publishedForm();

    $response = $this->post('/f/'.$form->public_id, fillPayload([
        'full_name' => 'A', // below minLength 2
        'email' => 'not-an-email',
    ]));

    $response->assertSessionHasErrors(['full_name', 'email']);
    expect($form->submissions()->count())->toBe(0);
});

it('rejects submissions to unpublished forms', function () {
    $form = Form::factory()->draft()->create();

    // Guests can't even discover the draft; the owner gets a clear 403.
    $this->post('/f/'.$form->public_id, fillPayload(['full_name' => 'X']))
        ->assertNotFound();

    $this->actingAs($form->user)
        ->post('/f/'.$form->public_id, fillPayload(['full_name' => 'X']))
        ->assertForbidden();
});

it('silently swallows honeypot submissions', function () {
    $form = publishedForm();

    $payload = fillPayload(['full_name' => 'Bot', 'email' => 'bot@spam.io']);
    $payload['_hp_website'] = 'https://spam.example';

    $this->post('/f/'.$form->public_id, $payload)
        ->assertRedirect()
        ->assertSessionHas('fill_success', true);

    expect($form->submissions()->count())->toBe(0);
});

it('silently swallows suspiciously fast submissions', function () {
    $form = publishedForm();

    $payload = [
        'full_name' => 'Speedy',
        'email' => 'speedy@example.com',
        '_fill_token' => Crypt::encryptString((string) now()->timestamp),
        '_hp_website' => '',
    ];

    $this->post('/f/'.$form->public_id, $payload)
        ->assertRedirect()
        ->assertSessionHas('fill_success', true);

    expect($form->submissions()->count())->toBe(0);
});

it('discards values for fields hidden by conditions', function () {
    $form = publishedForm([
        'sections' => [[
            'fields' => [
                1 => [
                    'id' => 'fld_cond0001',
                    'type' => 'text',
                    'key' => 'hidden_extra',
                    'label' => 'Only when name is "other"',
                    'placeholder' => null,
                    'help' => null,
                    'default' => null,
                    'required' => false,
                    'options' => [],
                    'validation' => null,
                    'conditions' => [
                        'logic' => 'all',
                        'rules' => [['field' => 'full_name', 'operator' => 'equals', 'value' => 'other']],
                    ],
                ],
            ],
        ]],
    ]);

    $this->post('/f/'.$form->public_id, fillPayload([
        'full_name' => 'Regular Name',
        'hidden_extra' => 'should never be stored',
    ]))->assertSessionHas('fill_success', true);

    expect($form->submissions()->sole()->data)->not->toHaveKey('hidden_extra');
});

it('stores uploaded files and serves them to the owner only', function () {
    Storage::fake();

    $form = publishedForm([
        'sections' => [[
            'fields' => [
                1 => [
                    'id' => 'fld_file0001',
                    'type' => 'file',
                    'key' => 'attachment',
                    'label' => 'Attachment',
                    'placeholder' => null,
                    'help' => null,
                    'default' => null,
                    'required' => true,
                    'options' => [],
                    'validation' => ['mimes' => ['pdf'], 'maxSizeKb' => 2048],
                    'conditions' => null,
                ],
            ],
        ]],
    ]);

    $this->post('/f/'.$form->public_id, fillPayload([
        'full_name' => 'File Sender',
        'attachment' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    ]))->assertSessionHas('fill_success', true);

    $file = $form->submissions()->sole()->files()->sole();

    Storage::assertExists($file->path);

    // Wrong extension is rejected.
    $this->post('/f/'.$form->public_id, fillPayload([
        'full_name' => 'Bad File',
        'attachment' => UploadedFile::fake()->create('script.exe', 10),
    ]))->assertSessionHasErrors('attachment');

    // Owner can download; strangers cannot.
    $this->actingAs($form->user)
        ->get(route('submissions.file', $file->id))
        ->assertOk();
    $this->actingAs(User::factory()->create())
        ->get(route('submissions.file', $file->id))
        ->assertForbidden();
});

it('enforces the daily submission cap', function () {
    $form = publishedForm([
        'settings' => ['max_per_day' => 1],
    ]);

    $this->post('/f/'.$form->public_id, fillPayload([
        'full_name' => 'First One',
        'email' => 'one@example.com',
    ]))->assertSessionHas('fill_success', true);

    $this->post('/f/'.$form->public_id, fillPayload([
        'full_name' => 'Second One',
        'email' => 'two@example.com',
    ]))->assertSessionHasErrors('_form');

    expect($form->submissions()->count())->toBe(1);
});

it('records start and step beacons but rejects junk', function () {
    $form = publishedForm();

    $this->post('/f/'.$form->public_id.'/event', ['type' => 'start'])->assertNoContent();
    $this->post('/f/'.$form->public_id.'/event', ['type' => 'start'])->assertNoContent();
    $this->post('/f/'.$form->public_id.'/event', ['type' => 'step', 'step' => 1])->assertNoContent();
    $this->post('/f/'.$form->public_id.'/event', ['type' => 'submit'])->assertSessionHasErrors('type');

    expect($form->events()->where('type', 'start')->count())->toBe(1)
        ->and($form->events()->where('type', 'step')->count())->toBe(1);
});
