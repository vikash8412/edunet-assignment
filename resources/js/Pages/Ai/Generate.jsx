import { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';

const POLL_MS = 2000;
const HANDOFF_KEY = 'ai-generated-schema';

const EXAMPLES = [
    'internship application with education history, skills and resume upload',
    'customer feedback survey with a rating and an optional comment',
    'event registration with dietary preferences and a t-shirt size',
    'job application with work experience, references and a cover letter',
];

/**
 * Standalone "create a form from a prompt" entry point, reached from the
 * Forms list next to "New form". On success the generated schema is handed
 * off (sessionStorage — there's no form row yet to attach it to) and the
 * user lands in the builder wizard with it already applied.
 */
export default function Generate() {
    const [prompt, setPrompt] = useState('');
    const [generation, setGeneration] = useState(null);
    const [error, setError] = useState(null);
    const pollRef = useRef(null);

    const busy = generation && ['queued', 'running'].includes(generation.status);

    useEffect(() => () => clearInterval(pollRef.current), []);

    const start = async (e) => {
        e.preventDefault();
        setError(null);
        setGeneration(null);

        try {
            const { data } = await axios.post(route('ai.generations.store'), {
                prompt,
                mode: 'create',
            });

            setGeneration({ id: data.id, status: 'queued' });

            pollRef.current = setInterval(async () => {
                try {
                    const { data: status } = await axios.get(
                        route('ai.generations.show', data.id),
                    );
                    setGeneration(status);

                    if (['done', 'failed'].includes(status.status)) {
                        clearInterval(pollRef.current);
                    }
                } catch {
                    clearInterval(pollRef.current);
                    setError('Lost contact while polling — try again.');
                    setGeneration(null);
                }
            }, POLL_MS);
        } catch (err) {
            setError(
                err.response?.data?.message ??
                    'Could not start the generation. Try again.',
            );
        }
    };

    const useIt = () => {
        sessionStorage.setItem(HANDOFF_KEY, JSON.stringify(generation.result_schema));
        router.visit(route('forms.create', { from: 'ai' }));
    };

    const fieldCount = (s) =>
        s.sections.reduce((n, section) => n + section.fields.length, 0);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <span className="page-eyebrow d-block mb-1">
                        <i className="bi bi-stars me-1" style={{ color: 'var(--ai-accent)' }} />
                        AI-assisted
                    </span>
                    <h3 className="page-title mb-0">Describe your form</h3>
                </div>
            }
        >
            <Head title="Generate with AI" />

            <div className="card">
                <div className="card-body p-4">
                    <p className="text-secondary">
                        Describe the form you need in plain language — the AI picks
                        sensible field types, labels, placeholders, options and
                        validation. You'll land in the builder to review and adjust
                        everything before saving.
                    </p>

                    <form onSubmit={start}>
                        <textarea
                            className="form-control mb-2"
                            rows={3}
                            placeholder='e.g. "internship application with education history, skills and resume upload"'
                            value={prompt}
                            onChange={(e) => setPrompt(e.target.value)}
                            disabled={busy}
                            autoFocus
                        />

                        <div className="d-flex flex-wrap gap-1 mb-3">
                            {EXAMPLES.map((example) => (
                                <button
                                    key={example}
                                    type="button"
                                    className="btn btn-sm btn-outline-secondary"
                                    disabled={busy}
                                    onClick={() => setPrompt(example)}
                                >
                                    {example}
                                </button>
                            ))}
                        </div>

                        <div className="d-flex align-items-center gap-2">
                            <button
                                type="submit"
                                className="btn btn-primary"
                                disabled={busy || prompt.trim().length < 5}
                            >
                                {busy ? (
                                    <>
                                        <span className="spinner-border spinner-border-sm me-2" />
                                        {generation.status === 'queued'
                                            ? 'Waiting for worker…'
                                            : 'Generating…'}
                                    </>
                                ) : (
                                    <>
                                        <i className="bi bi-stars me-1" />
                                        Generate form
                                    </>
                                )}
                            </button>
                            <Link href={route('forms.index')} className="btn btn-outline-secondary">
                                Cancel
                            </Link>
                        </div>
                    </form>

                    {error && (
                        <div className="alert alert-danger small mt-3 mb-0">{error}</div>
                    )}

                    {generation?.status === 'failed' && (
                        <div className="alert alert-danger small mt-3 mb-0">
                            <strong>Generation failed:</strong> {generation.error}
                        </div>
                    )}

                    {generation?.status === 'done' && (
                        <div className="alert alert-success mt-3 mb-0">
                            <strong>“{generation.result_schema.title}” is ready</strong> —{' '}
                            {generation.result_schema.sections.length} section(s),{' '}
                            {fieldCount(generation.result_schema)} field(s).
                            <div className="text-secondary small mt-1">
                                {generation.stats.model} · {generation.stats.attempts} attempt(s) ·{' '}
                                {generation.stats.prompt_tokens ?? '?'}+
                                {generation.stats.completion_tokens ?? '?'} tokens ·{' '}
                                {(generation.stats.latency_ms / 1000).toFixed(1)}s
                            </div>
                            {generation.warnings.length > 0 && (
                                <ul className="mb-0 mt-2 text-warning-emphasis small">
                                    {generation.warnings.map((warning, i) => (
                                        <li key={i}>{warning}</li>
                                    ))}
                                </ul>
                            )}
                            <button
                                type="button"
                                className="btn btn-success btn-sm mt-3"
                                onClick={useIt}
                            >
                                <i className="bi bi-arrow-right-circle me-1" />
                                Open in builder
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

export { HANDOFF_KEY };
