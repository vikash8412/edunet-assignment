<?php

use App\Models\Form;
use App\Models\User;

it('aggregates the funnel from unique sessions', function () {
    $form = Form::factory()->create();

    // 3 viewers, 2 starters, 1 submitter; one noisy duplicate view.
    foreach (['a', 'b', 'c'] as $visitor) {
        $form->events()->create([
            'session_hash' => $visitor, 'type' => 'view', 'created_at' => now(),
        ]);
    }
    $form->events()->create(['session_hash' => 'a', 'type' => 'view', 'created_at' => now()]);
    foreach (['a', 'b'] as $visitor) {
        $form->events()->create([
            'session_hash' => $visitor, 'type' => 'start', 'created_at' => now(),
        ]);
    }
    $form->events()->create(['session_hash' => 'a', 'type' => 'submit', 'created_at' => now()]);

    $this->actingAs($form->user)
        ->get(route('forms.analytics', $form))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Forms/Analytics')
                ->where('funnel.views', 3)
                ->where('funnel.starts', 2)
                ->where('funnel.submits', 1)
                ->where('funnel.start_rate', 67)
                ->where('funnel.completion_rate', 50)
                ->has('timeline', 14),
        );
});

it('reports step drop-off for multi-step forms', function () {
    $schema = baseSchema([
        'settings' => ['multi_step' => true],
    ]);
    $schema['sections'][] = [
        'id' => 'sec_step0002',
        'title' => 'Step two',
        'description' => null,
        'fields' => [[
            'id' => 'fld_step0002', 'type' => 'text', 'key' => 'extra', 'label' => 'Extra',
        ]],
    ];

    $form = Form::factory()->create(['schema' => $schema]);

    foreach (['a', 'b', 'c'] as $visitor) {
        $form->events()->create([
            'session_hash' => $visitor, 'type' => 'start', 'created_at' => now(),
        ]);
    }
    $form->events()->create([
        'session_hash' => 'a', 'type' => 'step', 'step' => 1, 'created_at' => now(),
    ]);

    $this->actingAs($form->user)
        ->get(route('forms.analytics', $form))
        ->assertInertia(
            fn ($page) => $page
                ->where('steps.0.sessions', 3)
                ->where('steps.1.sessions', 1),
        );
});

it('keeps analytics private to the owner', function () {
    $form = Form::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forms.analytics', $form))
        ->assertForbidden();
});
