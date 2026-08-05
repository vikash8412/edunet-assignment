<?php

use App\Models\Form;
use App\Models\User;
use App\Services\Submissions\SubmissionService;

function seedSubmission(Form $form, array $data): void
{
    app(SubmissionService::class)->store($form, $data, null, null, null);
}

it('lists submissions with schema-derived columns', function () {
    $form = Form::factory()->create();
    seedSubmission($form, ['full_name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $this->actingAs($form->user)
        ->get(route('forms.submissions.index', $form))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Submissions/Index')
                ->has('submissions.data', 1)
                ->where('submissions.data.0.data.full_name', 'Grace Hopper')
                ->where('columns.0.key', 'full_name'),
        );
});

it('searches submissions by answer text', function () {
    $form = Form::factory()->create();
    seedSubmission($form, ['full_name' => 'Grace Hopper', 'email' => 'grace@example.com']);
    seedSubmission($form, ['full_name' => 'Alan Turing', 'email' => 'alan@example.com']);

    $this->actingAs($form->user)
        ->get(route('forms.submissions.index', ['form' => $form->id, 'q' => 'Turing']))
        ->assertInertia(
            fn ($page) => $page
                ->has('submissions.data', 1)
                ->where('submissions.data.0.data.full_name', 'Alan Turing'),
        );
});

it('exports submissions as CSV', function () {
    $form = Form::factory()->create();
    seedSubmission($form, ['full_name' => 'Grace Hopper', 'email' => 'grace@example.com']);

    $response = $this->actingAs($form->user)
        ->get(route('forms.submissions.export', $form));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Full name')
        ->toContain('Grace Hopper')
        ->toContain('grace@example.com');
});

it('blocks strangers from submissions and exports', function () {
    $form = Form::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('forms.submissions.index', $form))
        ->assertForbidden();
    $this->actingAs($stranger)
        ->get(route('forms.submissions.export', $form))
        ->assertForbidden();
});
