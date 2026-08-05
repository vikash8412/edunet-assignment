<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Services\Schema\FieldTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubmissionController extends Controller
{
    public function index(Request $request, Form $form): Response
    {
        Gate::authorize('view', $form);

        $search = trim((string) $request->query('q', ''));

        $submissions = $form->submissions()
            ->with('files')
            ->when($search !== '', fn ($query) => $this->applySearch($query, $search))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Submission $submission) => [
                'id' => $submission->id,
                'data' => $submission->data,
                'files' => $submission->files->map(fn (SubmissionFile $file) => [
                    'id' => $file->id,
                    'field_key' => $file->field_key,
                    'original_name' => $file->original_name,
                ]),
                'created_at' => $submission->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Submissions/Index', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'public_url' => $form->publicUrl(),
            ],
            'columns' => $this->columns($form->schema),
            'submissions' => $submissions,
            'search' => $search,
        ]);
    }

    public function export(Request $request, Form $form): StreamedResponse
    {
        Gate::authorize('view', $form);

        $columns = $this->columns($form->schema);
        $filename = str($form->title)->slug()->limit(40, '')->toString() ?: 'form';

        return response()->streamDownload(function () use ($form, $columns) {
            $out = fopen('php://output', 'w');
            // BOM so Excel opens UTF-8 correctly.
            fwrite($out, "\u{FEFF}");

            fputcsv($out, ['#', 'Submitted at', ...array_column($columns, 'label')]);

            $form->submissions()->orderBy('id')->chunk(500, function ($chunk) use ($out, $columns) {
                foreach ($chunk as $submission) {
                    $row = [$submission->id, $submission->created_at->format('Y-m-d H:i:s')];

                    foreach ($columns as $column) {
                        $value = $submission->data[$column['key']] ?? '';
                        $row[] = is_array($value) ? implode('; ', $value) : (string) $value;
                    }

                    fputcsv($out, $row);
                }
            });

            fclose($out);
        }, "{$filename}-submissions.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function file(Request $request, SubmissionFile $file): StreamedResponse
    {
        $form = $file->submission->form;

        Gate::authorize('view', $form);

        abort_unless(Storage::exists($file->path), 404);

        return Storage::download($file->path, $file->original_name);
    }

    /** Input-field columns from the form's current schema. */
    private function columns(array $schema): array
    {
        $columns = [];

        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                if (FieldTypes::isInput($field['type'])) {
                    $columns[] = [
                        'key' => $field['key'],
                        'label' => $field['label'],
                        'type' => $field['type'],
                    ];
                }
            }
        }

        return $columns;
    }

    private function applySearch($query, string $search)
    {
        $driver = DB::connection()->getDriverName();

        // FULLTEXT needs MySQL/MariaDB; local SQLite and defensive shared
        // hosts fall back to LIKE.
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return $query->whereFullText('search_text', $search);
        }

        return $query->where('search_text', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%');
    }
}
