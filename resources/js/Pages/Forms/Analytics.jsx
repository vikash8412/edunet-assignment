import { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { Bar } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

/**
 * Chart.js paints to a <canvas> — it can't read CSS custom properties the
 * way the rest of the UI does, so we resolve the active theme's tokens once
 * per render and pass them in as plain values. Re-resolves whenever the
 * user's OS theme or the in-app toggle changes.
 */
function useChartTokens() {
    const resolve = () => {
        const styles = getComputedStyle(document.documentElement);
        const get = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;
        return {
            accent: get('--accent', '#5b47e0'),
            ink: get('--ink', '#1b1836'),
            inkFaint: get('--ink-faint', '#918cb4'),
            border: get('--border', '#e7e4f4'),
        };
    };

    const [tokens, setTokens] = useState(resolve);

    useEffect(() => {
        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const refresh = () => setTokens(resolve());

        media.addEventListener('change', refresh);

        // The in-app theme toggle stamps data-theme on <html>; watch for it.
        const observer = new MutationObserver(refresh);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        return () => {
            media.removeEventListener('change', refresh);
            observer.disconnect();
        };
    }, []);

    return tokens;
}

function StatTile({ label, value, hint }) {
    return (
        <div className="col-6 col-lg">
            <div className="card h-100">
                <div className="card-body py-3">
                    <div className="small text-secondary">{label}</div>
                    <div className="fs-3 fw-bold" style={{ color: 'var(--ink)' }}>
                        {value}
                    </div>
                    {hint && <div className="small text-secondary">{hint}</div>}
                </div>
            </div>
        </div>
    );
}

function formatDuration(seconds) {
    if (seconds === null) return '—';
    if (seconds < 60) return `${seconds}s`;
    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

export default function Analytics({
    form,
    windowDays,
    funnel,
    timeline,
    steps,
    median_seconds,
    total_submissions,
}) {
    const t = useChartTokens();

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: t.ink,
                padding: 10,
                cornerRadius: 6,
                displayColors: false,
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: t.inkFaint, font: { size: 11 } },
                border: { color: t.border },
            },
            y: {
                beginAtZero: true,
                grid: { color: t.border },
                ticks: { color: t.inkFaint, font: { size: 11 }, precision: 0 },
                border: { display: false },
            },
        },
    };

    const horizontalOptions = {
        ...baseOptions,
        indexAxis: 'y',
        scales: {
            x: { ...baseOptions.scales.y, grid: { color: t.border } },
            y: { ...baseOptions.scales.x, grid: { display: false } },
        },
    };

    const funnelData = {
        labels: ['Viewed', 'Started', 'Submitted'],
        datasets: [
            {
                data: [funnel.views, funnel.starts, funnel.submits],
                backgroundColor: t.accent,
                borderRadius: 4,
                maxBarThickness: 42,
            },
        ],
    };

    const timelineData = {
        labels: timeline.map((point) => point.day),
        datasets: [
            {
                data: timeline.map((point) => point.count),
                backgroundColor: t.accent,
                borderRadius: 3,
                maxBarThickness: 24,
            },
        ],
    };

    const stepsData = steps && {
        labels: steps.map((step) => step.title),
        datasets: [
            {
                data: steps.map((step) => step.sessions),
                backgroundColor: t.accent,
                borderRadius: 4,
                maxBarThickness: 32,
            },
        ],
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <span className="page-eyebrow d-block mb-1">Insights</span>
                        <h3 className="page-title mb-0">Analytics</h3>
                        <div className="text-secondary small">
                            {form.title} · last {windowDays} days
                        </div>
                    </div>
                    <Link
                        href={route('forms.submissions.index', form.id)}
                        className="btn btn-outline-secondary"
                    >
                        <i className="bi bi-inbox me-1" />
                        Submissions
                    </Link>
                </div>
            }
        >
            <Head title={`Analytics — ${form.title}`} />

            <div className="row g-3 mb-3">
                <StatTile label="Views" value={funnel.views} hint="unique visitors" />
                <StatTile
                    label="Starts"
                    value={funnel.starts}
                    hint={
                        funnel.start_rate !== null
                            ? `${funnel.start_rate}% of views`
                            : 'no views yet'
                    }
                />
                <StatTile
                    label="Submissions"
                    value={funnel.submits}
                    hint={
                        funnel.completion_rate !== null
                            ? `${funnel.completion_rate}% of starts`
                            : 'no starts yet'
                    }
                />
                <StatTile
                    label="Median fill time"
                    value={formatDuration(median_seconds)}
                    hint="start to submit"
                />
                <StatTile
                    label="All-time total"
                    value={total_submissions}
                    hint="submissions"
                />
            </div>

            <div className="row g-3">
                <div className="col-lg-5">
                    <div className="card h-100">
                        <div className="card-body">
                            <h6 className="mb-3" style={{ color: 'var(--ink)' }}>
                                Funnel — views → starts → submissions
                            </h6>
                            <div style={{ height: 220 }}>
                                <Bar data={funnelData} options={horizontalOptions} />
                            </div>
                        </div>
                    </div>
                </div>

                <div className="col-lg-7">
                    <div className="card h-100">
                        <div className="card-body">
                            <h6 className="mb-3" style={{ color: 'var(--ink)' }}>
                                Submissions — last 14 days
                            </h6>
                            <div style={{ height: 220 }}>
                                <Bar data={timelineData} options={baseOptions} />
                            </div>
                        </div>
                    </div>
                </div>

                {stepsData && (
                    <div className="col-12">
                        <div className="card">
                            <div className="card-body">
                                <h6 className="mb-1" style={{ color: 'var(--ink)' }}>
                                    Step drop-off
                                </h6>
                                <p className="small text-secondary mb-3">
                                    Unique sessions that reached each step of the
                                    multi-step form.
                                </p>
                                <div style={{ height: 200 }}>
                                    <Bar data={stepsData} options={horizontalOptions} />
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            <p className="text-secondary small mt-3 mb-0">
                <i className="bi bi-shield-check me-1" />
                Counted from anonymous beacons (salted daily hashes) — no cookies,
                no personal data. Spam protection: rate limiting, honeypot,
                minimum fill time{funnel.max_per_day ? ', daily cap' : ''}.
            </p>
        </AuthenticatedLayout>
    );
}
