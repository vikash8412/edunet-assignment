<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\FormVersion;
use App\Services\Schema\SchemaDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FormVersionController extends Controller
{
    public function index(Request $request, Form $form, SchemaDiff $differ): Response
    {
        Gate::authorize('view', $form);

        $versions = $form->versions()
            ->with('author:id,name')
            ->orderByDesc('version')
            ->get();

        $byVersion = $versions->keyBy('version');

        $items = $versions->map(function (FormVersion $version) use ($byVersion, $differ, $form) {
            $previous = $byVersion->get($version->version - 1);

            return [
                'id' => $version->id,
                'version' => $version->version,
                'source' => $version->source,
                'label' => $version->label,
                'author' => $version->author?->name,
                'created_at' => $version->created_at->format('Y-m-d H:i'),
                'is_current' => $version->version === $form->current_version,
                'field_count' => array_sum(array_map(
                    fn ($section) => count($section['fields']),
                    $version->schema['sections'],
                )),
                'diff' => $previous
                    ? $differ->diff($previous->schema, $version->schema)
                    : null,
            ];
        });

        return Inertia::render('Forms/Versions', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'current_version' => $form->current_version,
            ],
            'versions' => $items,
        ]);
    }

    /**
     * Rollback = append the old snapshot as a NEW version. History is never
     * rewritten, and submissions keep pointing at the versions they used.
     */
    public function rollback(Request $request, Form $form, FormVersion $version): RedirectResponse
    {
        Gate::authorize('update', $form);

        abort_unless($version->form_id === $form->id, 404);
        abort_if($version->version === $form->current_version, 422, 'Already the current version.');

        $form->saveSchemaVersion(
            $version->schema,
            $request->user()->id,
            source: 'rollback',
            label: "Rollback to v{$version->version}",
        );

        return redirect()
            ->route('forms.versions.index', $form)
            ->with('success', "Rolled back to version {$version->version} (as new version {$form->current_version}).");
    }
}
