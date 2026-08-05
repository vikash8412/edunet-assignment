<?php

namespace Database\Seeders;

use App\Models\Form;
use App\Models\User;
use App\Services\Submissions\SubmissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $feedback = $this->feedbackForm($demo);
        $this->internshipForm($demo);

        $this->seedSubmissions($feedback);
    }

    private function feedbackForm(User $user): Form
    {
        $schema = [
            'title' => 'Customer Feedback Survey',
            'description' => 'Tell us how we did — it takes less than two minutes.',
            'settings' => [
                'multi_step' => false,
                'success_message' => 'Thanks for the feedback!',
                'submit_label' => 'Send feedback',
                'max_per_day' => null,
            ],
            'sections' => [
                [
                    'id' => 'sec_'.Str::random(8),
                    'title' => 'Your experience',
                    'description' => null,
                    'fields' => [
                        $this->field('text', 'full_name', 'Full name', required: true, extra: [
                            'placeholder' => 'Jane Doe',
                            'validation' => ['minLength' => 2, 'maxLength' => 100],
                        ]),
                        $this->field('email', 'email', 'Email address', required: true, extra: [
                            'placeholder' => 'jane@example.com',
                        ]),
                        $this->field('rating', 'overall_rating', 'Overall rating', required: true, extra: [
                            'validation' => ['max' => 5],
                        ]),
                        $this->field('radio', 'recommend', 'Would you recommend us?', required: true, extra: [
                            'options' => [
                                ['label' => 'Yes', 'value' => 'yes'],
                                ['label' => 'No', 'value' => 'no'],
                            ],
                        ]),
                        $this->field('textarea', 'improvement', 'What should we improve?', extra: [
                            'placeholder' => 'Be brutally honest…',
                            'conditions' => [
                                'logic' => 'all',
                                'rules' => [
                                    ['field' => 'recommend', 'operator' => 'equals', 'value' => 'no'],
                                ],
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        $form = $user->forms()->make(['title' => $schema['title'], 'status' => Form::STATUS_PUBLISHED]);
        $form->schema = $schema;
        $form->save();
        $form->saveSchemaVersion($schema, $user->id);

        return $form;
    }

    private function internshipForm(User $user): Form
    {
        $schema = [
            'title' => 'Internship Application',
            'description' => 'Summer 2026 engineering internship — applications close soon.',
            'settings' => [
                'multi_step' => true,
                'success_message' => 'Application received. We reply within a week.',
                'submit_label' => 'Submit application',
                'max_per_day' => 100,
            ],
            'sections' => [
                [
                    'id' => 'sec_'.Str::random(8),
                    'title' => 'Personal information',
                    'description' => null,
                    'fields' => [
                        $this->field('text', 'full_name', 'Full name', required: true),
                        $this->field('email', 'email', 'Email address', required: true),
                        $this->field('phone', 'phone', 'Phone number', required: true),
                        $this->field('date', 'available_from', 'Available from', extra: [
                            'validation' => ['minDate' => '2026-05-01'],
                        ]),
                    ],
                ],
                [
                    'id' => 'sec_'.Str::random(8),
                    'title' => 'Education & skills',
                    'description' => null,
                    'fields' => [
                        $this->field('dropdown', 'degree', 'Current degree', required: true, extra: [
                            'options' => [
                                ['label' => 'Bachelor', 'value' => 'bachelor'],
                                ['label' => 'Master', 'value' => 'master'],
                                ['label' => 'Other', 'value' => 'other'],
                            ],
                        ]),
                        $this->field('text', 'degree_other', 'Tell us about your degree', extra: [
                            'conditions' => [
                                'logic' => 'all',
                                'rules' => [
                                    ['field' => 'degree', 'operator' => 'equals', 'value' => 'other'],
                                ],
                            ],
                        ]),
                        $this->field('checkbox', 'skills', 'Skills', required: true, extra: [
                            'options' => [
                                ['label' => 'PHP / Laravel', 'value' => 'php'],
                                ['label' => 'JavaScript / React', 'value' => 'js'],
                                ['label' => 'Python', 'value' => 'python'],
                                ['label' => 'SQL', 'value' => 'sql'],
                            ],
                            'validation' => ['max' => 4],
                        ]),
                        $this->field('number', 'experience_years', 'Years of experience', extra: [
                            'validation' => ['min' => 0, 'max' => 30, 'integer' => true],
                        ]),
                    ],
                ],
                [
                    'id' => 'sec_'.Str::random(8),
                    'title' => 'Documents',
                    'description' => 'PDF only, max 2 MB.',
                    'fields' => [
                        $this->field('file', 'resume', 'Resume / CV', required: true, extra: [
                            'validation' => ['mimes' => ['pdf'], 'maxSizeKb' => 2048],
                        ]),
                        $this->field('text', 'portfolio', 'Portfolio URL', extra: [
                            'validation' => ['url' => true],
                            'placeholder' => 'https://…',
                        ]),
                    ],
                ],
            ],
        ];

        $form = $user->forms()->make(['title' => $schema['title'], 'status' => Form::STATUS_PUBLISHED]);
        $form->schema = $schema;
        $form->save();
        $form->saveSchemaVersion($schema, $user->id);

        return $form;
    }

    private function seedSubmissions(Form $form): void
    {
        $service = new SubmissionService();

        $samples = [
            ['full_name' => 'Aarav Sharma', 'email' => 'aarav@example.com', 'overall_rating' => 5, 'recommend' => 'yes'],
            ['full_name' => 'Priya Patel', 'email' => 'priya@example.com', 'overall_rating' => 4, 'recommend' => 'yes'],
            ['full_name' => 'Rohan Gupta', 'email' => 'rohan@example.com', 'overall_rating' => 2, 'recommend' => 'no', 'improvement' => 'Faster support responses, please.'],
            ['full_name' => 'Sneha Iyer', 'email' => 'sneha@example.com', 'overall_rating' => 5, 'recommend' => 'yes'],
            ['full_name' => 'Vikram Singh', 'email' => 'vikram@example.com', 'overall_rating' => 3, 'recommend' => 'no', 'improvement' => 'The checkout flow is confusing.'],
        ];

        foreach ($samples as $i => $data) {
            $submission = $service->store(
                $form,
                $data,
                hash('sha256', "seed-{$i}"),
                'Seeder',
                now()->subDays(count($samples) - $i)->subMinutes(3),
            );
            $submission->created_at = now()->subDays(count($samples) - $i);
            $submission->save();

            // Funnel events: every submitter viewed and started first.
            foreach (['view', 'start', 'submit'] as $type) {
                $form->events()->create([
                    'session_hash' => hash('sha256', "seed-{$i}"),
                    'type' => $type,
                    'step' => null,
                    'created_at' => $submission->created_at,
                ]);
            }
        }

        // Plus some visitors who never finished — that's the drop-off story.
        for ($i = 0; $i < 9; $i++) {
            $types = $i < 5 ? ['view'] : ['view', 'start'];
            foreach ($types as $type) {
                $form->events()->create([
                    'session_hash' => hash('sha256', "drop-{$i}"),
                    'type' => $type,
                    'step' => null,
                    'created_at' => now()->subDays($i % 5),
                ]);
            }
        }
    }

    private function field(
        string $type,
        string $key,
        string $label,
        bool $required = false,
        array $extra = [],
    ): array {
        return array_merge([
            'id' => 'fld_'.Str::random(8),
            'type' => $type,
            'key' => $key,
            'label' => $label,
            'placeholder' => null,
            'help' => null,
            'default' => null,
            'required' => $required,
            'options' => [],
            'validation' => null,
            'conditions' => null,
        ], $extra);
    }
}
