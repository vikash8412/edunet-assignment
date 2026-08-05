<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\Schema\SchemaValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function __construct(private readonly SchemaValidator $validator)
    {
    }

    public function index(Request $request): Response
    {
        $forms = Form::forTenant($request->user()->tenantId())
            ->withCount('submissions')
            ->latest('updated_at')
            ->paginate(10)
            ->through(fn (Form $form) => [
                'id' => $form->id,
                'title' => $form->title,
                'status' => $form->status,
                'public_id' => $form->public_id,
                'public_url' => $form->publicUrl(),
                'current_version' => $form->current_version,
                'submissions_count' => $form->submissions_count,
                'updated_at' => $form->updated_at->diffForHumans(),
            ]);

        return Inertia::render('Forms/Index', ['forms' => $forms]);
    }

    public function create(): Response
    {
        return Inertia::render('Forms/Wizard', [
            'mode' => 'create',
            'form' => null,
            'initialStep' => 'details',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $schema = $this->validatedSchema($request);

        $form = Form::createForTenant($request->user(), [
            'title' => $schema['title'],
            'schema' => $schema,
            'status' => $request->input('status') === 'published'
                ? Form::STATUS_PUBLISHED
                : Form::STATUS_DRAFT,
        ]);

        $form->saveSchemaVersion($schema, $request->user()->id);

        return redirect()
            ->route('forms.edit', ['form' => $form->id, 'step' => 'finish'])
            ->with('success', 'Form created.');
    }

    public function edit(Request $request, Form $form): Response
    {
        Gate::authorize('update', $form);

        return Inertia::render('Forms/Wizard', [
            'mode' => 'edit',
            'initialStep' => $request->query('step', 'details'),
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'status' => $form->status,
                'schema' => $form->schema,
                'public_id' => $form->public_id,
                'public_url' => $form->publicUrl(),
                'current_version' => $form->current_version,
                'submissions_count' => $form->submissions()->count(),
            ],
        ]);
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        Gate::authorize('update', $form);

        $schema = $this->validatedSchema($request);

        $status = $request->input('status');
        if (in_array($status, [Form::STATUS_DRAFT, Form::STATUS_PUBLISHED, Form::STATUS_ARCHIVED], true)) {
            $form->status = $status;
        }

        // Only append a version when the schema actually changed.
        if ($form->schema !== $schema) {
            $form->saveSchemaVersion($schema, $request->user()->id);
        } else {
            $form->save();
        }

        return redirect()
            ->route('forms.edit', ['form' => $form->id, 'step' => $request->input('return_step', 'finish')])
            ->with('success', 'Form saved.');
    }

    public function destroy(Request $request, Form $form): RedirectResponse
    {
        Gate::authorize('delete', $form);

        $form->delete();

        return redirect()->route('forms.index')->with('success', 'Form deleted.');
    }

    /**
     * Validate the submitted schema through the single schema gate.
     * Errors surface as standard validation errors under "schema".
     */
    private function validatedSchema(Request $request): array
    {
        $schema = $request->input('schema');

        if (! is_array($schema)) {
            throw ValidationException::withMessages(['schema' => 'Schema must be a JSON object.']);
        }

        $result = $this->validator->validate($schema);

        if (! $result->valid) {
            // Newline-joined so the Inertia error prop carries every message;
            // the builder UI splits them back into a list.
            throw ValidationException::withMessages([
                'schema' => implode("\n", $result->errors),
            ]);
        }

        return $schema;
    }
}
