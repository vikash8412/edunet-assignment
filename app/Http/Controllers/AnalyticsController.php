<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    private const WINDOW_DAYS = 30;

    public function show(Request $request, Form $form): Response
    {
        Gate::authorize('view', $form);

        $since = now()->subDays(self::WINDOW_DAYS)->startOfDay();

        // Funnel: unique visitors per stage (session-hashed, no PII).
        $funnel = $form->events()
            ->where('created_at', '>=', $since)
            ->selectRaw('type, count(distinct session_hash) as sessions')
            ->groupBy('type')
            ->pluck('sessions', 'type');

        $views = (int) ($funnel['view'] ?? 0);
        $starts = (int) ($funnel['start'] ?? 0);
        $submits = (int) ($funnel['submit'] ?? 0);

        // Submissions per day for the trend chart.
        $daily = $form->submissions()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw("date(created_at) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $timeline = collect(range(13, 0))->map(function ($daysAgo) use ($daily) {
            $day = now()->subDays($daysAgo)->toDateString();

            return [
                'day' => now()->subDays($daysAgo)->format('d M'),
                'count' => (int) ($daily[$day] ?? 0),
            ];
        })->values();

        // Multi-step drop-off: how many sessions reached each step.
        $steps = null;

        if ($form->schema['settings']['multi_step'] ?? false) {
            $reached = $form->events()
                ->where('created_at', '>=', $since)
                ->where('type', 'step')
                ->selectRaw('step, count(distinct session_hash) as sessions')
                ->groupBy('step')
                ->pluck('sessions', 'step');

            $steps = collect($form->schema['sections'])->values()->map(fn ($section, $i) => [
                'title' => $section['title'] ?: 'Step '.($i + 1),
                // Step 0 is reached by everyone who started.
                'sessions' => $i === 0 ? $starts : (int) ($reached[$i] ?? 0),
            ]);
        }

        // Median completion time from started_at -> created_at.
        $durations = $form->submissions()
            ->whereNotNull('started_at')
            ->where('created_at', '>=', $since)
            ->get(['started_at', 'created_at'])
            ->map(fn ($submission) => (int) abs($submission->created_at->diffInSeconds($submission->started_at)))
            ->sort()
            ->values();

        $median = $durations->isEmpty()
            ? null
            : $durations->get(intdiv($durations->count(), 2));

        return Inertia::render('Forms/Analytics', [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'public_url' => $form->publicUrl(),
            ],
            'windowDays' => self::WINDOW_DAYS,
            'funnel' => [
                'views' => $views,
                'starts' => $starts,
                'submits' => $submits,
                'start_rate' => $views > 0 ? round($starts / $views * 100) : null,
                'completion_rate' => $starts > 0 ? round($submits / $starts * 100) : null,
            ],
            'timeline' => $timeline,
            'steps' => $steps,
            'median_seconds' => $median,
            'total_submissions' => $form->submissions()->count(),
        ]);
    }
}
