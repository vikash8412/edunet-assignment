<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Form>
 */
class FormFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'schema' => [
                'title' => $title,
                'description' => fake()->sentence(8),
                'settings' => ['multi_step' => false],
                'sections' => [
                    [
                        'id' => 'sec_'.Str::random(8),
                        'title' => 'General',
                        'description' => null,
                        'fields' => [
                            [
                                'id' => 'fld_'.Str::random(8),
                                'type' => 'text',
                                'key' => 'full_name',
                                'label' => 'Full name',
                                'placeholder' => 'Jane Doe',
                                'help' => null,
                                'default' => null,
                                'required' => true,
                                'options' => [],
                                'validation' => ['minLength' => 2, 'maxLength' => 100],
                                'conditions' => null,
                            ],
                            [
                                'id' => 'fld_'.Str::random(8),
                                'type' => 'email',
                                'key' => 'email',
                                'label' => 'Email address',
                                'placeholder' => 'jane@example.com',
                                'help' => null,
                                'default' => null,
                                'required' => true,
                                'options' => [],
                                'validation' => null,
                                'conditions' => null,
                            ],
                        ],
                    ],
                ],
            ],
            'status' => 'published',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function configure(): static
    {
        return $this
            // tenant_id must be derived from whichever user_id actually
            // resolves — explicit, ->for($user), or the bare factory default
            // — so every existing call site keeps working without having to
            // pass tenant_id itself.
            ->afterMaking(function (Form $form) {
                if (! $form->tenant_id) {
                    $owner = User::find($form->user_id) ?? User::factory()->tenant()->create();
                    $form->tenant_id = $owner->tenantId();
                    $form->user_id = $owner->id;
                }
            })
            // Mirror the real save flow: every form starts at version 1.
            ->afterCreating(function (Form $form) {
                if ($form->versions()->count() === 0) {
                    $form->saveSchemaVersion($form->schema, $form->user_id);
                }
            });
    }
}
