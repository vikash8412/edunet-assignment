import { useEffect, useRef, useState } from 'react';
import axios from 'axios';

const POLL_MS = 2000;

/**
 * AI-assisted editing of an already-saved form (Builder step, edit mode
 * only): "add an emergency contact section", "make phone required",
 * "translate labels to Hindi". Creating a NEW form from a prompt is a
 * separate standalone flow — see Pages/Ai/Generate.jsx, reached from the
 * Forms list's "Generate with AI" button.
 */
export default function GeneratePanel({ formId, onApply }) {
    const [open, setOpen] = useState(false);
    const [prompt, setPrompt] = useState('');
    const [generation, setGeneration] = useState(null); // {id,status,...}
    const [error, setError] = useState(null);
    const pollRef = useRef(null);

    const busy =
        generation && ['queued', 'running'].includes(generation.status);

    useEffect(() => () => clearInterval(pollRef.current), []);

    const start = async () => {
        setError(null);
        setGeneration(null);

        try {
            const { data } = await axios.post(route('ai.generations.store'), {
                prompt,
                mode: 'edit',
                form_id: formId,
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
        } catch (e) {
            setError(
                e.response?.data?.message ??
                    'Could not start the generation. Try again.',
            );
        }
    };

    const apply = () => {
        onApply(generation.result_schema);
        setGeneration(null);
        setPrompt('');
    };

    const fieldCount = (s) =>
        s.sections.reduce((n, section) => n + section.fields.length, 0);

    return (
        <div className="card border-primary-subtle mb-3">
            <div
                className="card-header bg-white d-flex align-items-center justify-content-between cursor-pointer"
                onClick={() => setOpen(!open)}
            >
                <strong className="small">
                    <i className="bi bi-stars text-primary me-1" />
                    Edit with AI
                </strong>
                <i className={'bi small ' + (open ? 'bi-chevron-up' : 'bi-chevron-down')} />
            </div>

            {open && (
                <div className="card-body">
                    <textarea
                        className="form-control mb-2"
                        rows={2}
                        placeholder='Describe a change — e.g. "add an emergency contact section", "make phone required", "translate labels to Hindi"'
                        value={prompt}
                        onChange={(e) => setPrompt(e.target.value)}
                        disabled={busy}
                    />

                    <div className="d-flex align-items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            className="btn btn-primary btn-sm"
                            disabled={busy || prompt.trim().length < 5}
                            onClick={start}
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
                                    Update this form
                                </>
                            )}
                        </button>
                        <span className="small text-secondary">
                            The AI edits the current form.
                        </span>
                    </div>

                    {error && (
                        <div className="alert alert-danger small py-2 mt-2 mb-0">{error}</div>
                    )}

                    {generation?.status === 'failed' && (
                        <div className="alert alert-danger small py-2 mt-2 mb-0">
                            <strong>Generation failed:</strong> {generation.error}
                        </div>
                    )}

                    {generation?.status === 'done' && (
                        <div className="alert alert-success small mt-2 mb-0">
                            <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong>
                                        “{generation.result_schema.title}” is ready
                                    </strong>{' '}
                                    — {generation.result_schema.sections.length} section(s),{' '}
                                    {fieldCount(generation.result_schema)} field(s).
                                    <div className="text-secondary mt-1">
                                        {generation.stats.model} ·{' '}
                                        {generation.stats.attempts} attempt(s) ·{' '}
                                        {generation.stats.prompt_tokens ?? '?'}+
                                        {generation.stats.completion_tokens ?? '?'} tokens ·{' '}
                                        {(generation.stats.latency_ms / 1000).toFixed(1)}s
                                    </div>
                                    {generation.warnings.length > 0 && (
                                        <ul className="mb-0 mt-1 text-warning-emphasis">
                                            {generation.warnings.map((warning, i) => (
                                                <li key={i}>{warning}</li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                                <div className="d-flex gap-2">
                                    <button
                                        type="button"
                                        className="btn btn-success btn-sm"
                                        onClick={apply}
                                    >
                                        <i className="bi bi-check-lg me-1" />
                                        Apply to canvas
                                    </button>
                                    <button
                                        type="button"
                                        className="btn btn-outline-secondary btn-sm"
                                        onClick={() => setGeneration(null)}
                                    >
                                        Discard
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
