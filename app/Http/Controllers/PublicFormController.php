<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\Schema\RuleCompiler;
use App\Services\Submissions\SubmissionService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    /** Reject as spam anything submitted faster than a human plausibly could. */
    private const MIN_FILL_SECONDS = 3;

    public function __construct(
        private readonly RuleCompiler $compiler,
        private readonly SubmissionService $submissions,
    ) {
    }

    public function show(Request $request, string $publicId): Response
    {
        $form = $this->resolveForm($request, $publicId);

        if ($form->status === Form::STATUS_ARCHIVED || $form->tenant->isDisabled()) {
            return Inertia::render('Public/Closed', [
                'title' => $form->title,
            ]);
        }

        $this->logEvent($form, $request, 'view', dedupe: true);

        return Inertia::render('Public/Fill', [
            'publicId' => $form->public_id,
            'schema' => $form->schema,
            'preview' => $form->status !== Form::STATUS_PUBLISHED,
            // Signed server timestamp for the minimum-fill-time spam check.
            'fillToken' => Crypt::encryptString((string) now()->timestamp),
            'success' => (bool) $request->session()->get('fill_success'),
        ]);
    }

    public function store(Request $request, string $publicId): RedirectResponse
    {
        $form = $this->resolveForm($request, $publicId);

        abort_unless($form->isPublished(), 403, 'This form is not accepting responses.');
        abort_if($form->tenant->isDisabled(), 403, 'This form is not accepting responses.');

        // Honeypot: bots fill every input. Pretend success, store nothing.
        if ($request->filled('_hp_website')) {
            return $this->success($request);
        }

        $startedAt = $this->decodeFillToken($request->input('_fill_token'));

        if ($startedAt !== null && $startedAt->diffInSeconds(now()) < self::MIN_FILL_SECONDS) {
            return $this->success($request);
        }

        if ($this->dailyCapReached($form)) {
            return back()->withErrors([
                '_form' => 'This form has reached its response limit for today. Please try again tomorrow.',
            ]);
        }

        $schema = $form->schema;
        $submitted = $this->submittedValues($request, $schema);

        $compiled = $this->compiler->compile($schema, $submitted);

        // Values for fields hidden by conditions are discarded, never stored.
        $accepted = array_intersect_key(
            $submitted,
            array_filter($compiled['visible']),
        );

        $validated = Validator::make($accepted, $compiled['rules'], [], $compiled['attributes'])
            ->validate();

        $this->submissions->store(
            $form,
            $validated,
            hash('sha256', $request->ip().config('app.key')),
            $request->userAgent(),
            $startedAt,
        );

        $this->logEvent($form, $request, 'submit');

        return $this->success($request);
    }

    /**
     * Analytics beacons from the fill page: start (first interaction) and
     * step (multi-step navigation). Views and submits are logged server-side.
     */
    public function event(Request $request, string $publicId)
    {
        $form = Form::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'type' => 'required|in:start,step',
            'step' => 'nullable|integer|min:0|max:100',
        ]);

        $this->logEvent(
            $form,
            $request,
            $validated['type'],
            $validated['step'] ?? null,
            dedupe: $validated['type'] === 'start',
        );

        return response()->noContent();
    }

    private function resolveForm(Request $request, string $publicId): Form
    {
        $form = Form::where('public_id', $publicId)->firstOrFail();

        // Drafts are visible to any teammate in the owning tenant as a
        // preview, 404 for everyone else.
        $viewerTenantId = $request->user()?->tenantId();

        if ($form->status === Form::STATUS_DRAFT && $viewerTenantId !== $form->tenant_id) {
            abort(404);
        }

        return $form;
    }

    /** Collect submitted values for every input field key in the schema. */
    private function submittedValues(Request $request, array $schema): array
    {
        $values = [];

        foreach ($schema['sections'] as $section) {
            foreach ($section['fields'] as $field) {
                $key = $field['key'];

                if ($field['type'] === 'file') {
                    if ($request->hasFile($key)) {
                        $values[$key] = $request->file($key);
                    }
                    continue;
                }

                if ($request->has($key)) {
                    $value = $request->input($key);
                    // Normalise empty strings to null so "nullable" applies.
                    $values[$key] = $value === '' ? null : $value;
                }
            }
        }

        return $values;
    }

    private function decodeFillToken(?string $token): ?Carbon
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            return Carbon::createFromTimestamp((int) Crypt::decryptString($token));
        } catch (DecryptException) {
            return null;
        }
    }

    private function dailyCapReached(Form $form): bool
    {
        $cap = $form->schema['settings']['max_per_day'] ?? null;

        if (! $cap) {
            return false;
        }

        return $form->submissions()
            ->where('created_at', '>=', now()->startOfDay())
            ->count() >= $cap;
    }

    private function logEvent(
        Form $form,
        Request $request,
        string $type,
        ?int $step = null,
        bool $dedupe = false,
    ): void {
        // Salted daily visitor hash — no cookies, no raw PII stored.
        $sessionHash = hash('sha256', implode('|', [
            $request->ip(),
            (string) $request->userAgent(),
            $form->id,
            now()->toDateString(),
            config('app.key'),
        ]));

        if ($dedupe) {
            $exists = $form->events()
                ->where('session_hash', $sessionHash)
                ->where('type', $type)
                ->exists();

            if ($exists) {
                return;
            }
        }

        $form->events()->create([
            'session_hash' => $sessionHash,
            'type' => $type,
            'step' => $step,
            'created_at' => now(),
        ]);
    }

    private function success(Request $request): RedirectResponse
    {
        return back()->with('fill_success', true);
    }
}
