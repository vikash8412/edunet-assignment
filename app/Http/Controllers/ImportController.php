<?php

namespace App\Http\Controllers;

use App\Jobs\ParseImportJob;
use App\Models\Form;
use App\Models\Import;
use App\Services\Schema\SchemaValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Import/Wizard');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:docx,xlsx|max:10240',
        ]);

        $file = $request->file('file');
        $kind = strtolower($file->getClientOriginalExtension());

        $import = $request->user()->imports()->create([
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'path' => $file->store('imports'),
            'kind' => $kind,
            'status' => Import::STATUS_QUEUED,
        ]);

        ParseImportJob::dispatch($import->id);

        return response()->json(['id' => $import->id], 201);
    }

    /** Polling endpoint for the import wizard. */
    public function show(Request $request, Import $import): JsonResponse
    {
        abort_unless($import->user_id === $request->user()->id, 403);

        return response()->json([
            'id' => $import->id,
            'status' => $import->status,
            'original_name' => $import->original_name,
            'parsed_schema' => $import->status === Import::STATUS_READY
                ? $import->parsed_schema
                : null,
            'warnings' => $import->warnings ?? [],
            'error' => $import->error,
            'form_id' => $import->form_id,
        ]);
    }

    /**
     * Commit the (possibly user-corrected) parsed schema as a new form.
     */
    public function commit(Request $request, Import $import, SchemaValidator $validator): RedirectResponse
    {
        abort_unless($import->user_id === $request->user()->id, 403);
        abort_unless($import->status === Import::STATUS_READY, 409, 'This import is not ready to commit.');

        $schema = $request->input('schema');

        if (! is_array($schema)) {
            throw ValidationException::withMessages(['schema' => 'Schema must be a JSON object.']);
        }

        $result = $validator->validate($schema);

        if (! $result->valid) {
            throw ValidationException::withMessages([
                'schema' => implode("\n", $result->errors),
            ]);
        }

        $form = $request->user()->forms()->make([
            'title' => $schema['title'],
            'status' => Form::STATUS_DRAFT,
        ]);
        $form->schema = $schema;
        $form->save();
        $form->saveSchemaVersion($schema, $request->user()->id, source: 'import');

        $import->update(['status' => Import::STATUS_COMMITTED, 'form_id' => $form->id]);

        return redirect()
            ->route('forms.edit', ['form' => $form->id, 'step' => 'builder'])
            ->with('success', "Imported “{$form->title}” — review it and publish when ready.");
    }
}
