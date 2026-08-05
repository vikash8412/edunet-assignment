import { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { FIELD_TYPES } from '@/lib/fieldTypes';

const POLL_MS = 1500;
const INPUT_TYPE_OPTIONS = FIELD_TYPES.filter(
    (t) => !['heading', 'paragraph'].includes(t.type),
);

export default function Wizard() {
    const { errors } = usePage().props;
    const [phase, setPhase] = useState('upload'); // upload | parsing | mapping
    const [error, setError] = useState(null);
    const [importInfo, setImportInfo] = useState(null);
    const [schema, setSchema] = useState(null);
    const [dragOver, setDragOver] = useState(false);
    const [committing, setCommitting] = useState(false);
    const pollRef = useRef(null);
    const fileInputRef = useRef(null);

    useEffect(() => () => clearInterval(pollRef.current), []);

    const upload = async (file) => {
        if (!file) return;
        setError(null);

        const body = new FormData();
        body.append('file', file);

        try {
            const { data } = await axios.post(route('imports.store'), body);
            setPhase('parsing');

            pollRef.current = setInterval(async () => {
                try {
                    const { data: status } = await axios.get(route('imports.show', data.id));

                    if (status.status === 'ready') {
                        clearInterval(pollRef.current);
                        setImportInfo(status);
                        setSchema(status.parsed_schema);
                        setPhase('mapping');
                    } else if (status.status === 'failed') {
                        clearInterval(pollRef.current);
                        setError(status.error ?? 'Parsing failed.');
                        setPhase('upload');
                    }
                } catch {
                    clearInterval(pollRef.current);
                    setError('Lost contact while parsing — try again.');
                    setPhase('upload');
                }
            }, POLL_MS);
        } catch (e) {
            setError(
                e.response?.data?.message ??
                    'Upload failed. Only .docx and .xlsx up to 10 MB are accepted.',
            );
        }
    };

    const patchField = (sectionIndex, fieldIndex, patch) => {
        setSchema((current) => {
            const next = structuredClone(current);
            Object.assign(next.sections[sectionIndex].fields[fieldIndex], patch);
            return next;
        });
    };

    const removeField = (sectionIndex, fieldIndex) => {
        setSchema((current) => {
            const next = structuredClone(current);
            next.sections[sectionIndex].fields.splice(fieldIndex, 1);
            return next;
        });
    };

    const commit = () => {
        setCommitting(true);
        router.post(
            route('imports.commit', importInfo.id),
            { schema },
            { onFinish: () => setCommitting(false) },
        );
    };

    return (
        <AuthenticatedLayout header={<h3 className="page-title">Import a form</h3>}>
            <Head title="Import" />

            {phase === 'upload' && (
                <div className="card">
                    <div className="card-body p-4">
                        <p className="text-secondary">
                            Upload a Word document (headings become sections, questions
                            become fields, choice lists become options) or an Excel sheet
                            (a <code>Label | Type | Required | Options</code> template, or
                            a plain header row).
                        </p>

                        {error && (
                            <div className="alert alert-danger py-2 small">{error}</div>
                        )}

                        <div
                            className={
                                'builder-canvas text-center py-5 cursor-pointer ' +
                                (dragOver ? 'drag-over' : '')
                            }
                            onClick={() => fileInputRef.current?.click()}
                            onDragOver={(e) => {
                                e.preventDefault();
                                setDragOver(true);
                            }}
                            onDragLeave={() => setDragOver(false)}
                            onDrop={(e) => {
                                e.preventDefault();
                                setDragOver(false);
                                upload(e.dataTransfer.files[0]);
                            }}
                        >
                            <i className="bi bi-cloud-arrow-up fs-1 text-secondary d-block mb-2" />
                            <strong>Drop a .docx or .xlsx here</strong>
                            <div className="text-secondary small mt-1">
                                or click to browse — max 10 MB
                            </div>
                            <input
                                ref={fileInputRef}
                                type="file"
                                accept=".docx,.xlsx"
                                className="d-none"
                                onChange={(e) => upload(e.target.files[0])}
                            />
                        </div>
                    </div>
                </div>
            )}

            {phase === 'parsing' && (
                <div className="card">
                    <div className="card-body text-center py-5">
                        <div className="spinner-border text-primary mb-3" />
                        <h5>Parsing your document…</h5>
                        <p className="text-secondary small mb-0">
                            Deterministic parsing first; AI only helps with ambiguous
                            field types.
                        </p>
                    </div>
                </div>
            )}

            {phase === 'mapping' && schema && (
                <>
                    {importInfo.warnings.length > 0 && (
                        <div className="alert alert-warning small">
                            <strong>
                                <i className="bi bi-exclamation-triangle me-1" />
                                The parser flagged {importInfo.warnings.length} thing(s):
                            </strong>
                            <ul className="mb-0 mt-1">
                                {importInfo.warnings.map((warning, i) => (
                                    <li key={i}>{warning}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {errors?.schema && (
                        <div className="alert alert-danger small">
                            {String(errors.schema)
                                .split('\n')
                                .map((line, i) => (
                                    <div key={i}>{line}</div>
                                ))}
                        </div>
                    )}

                    <div className="card mb-3">
                        <div className="card-body">
                            <label className="form-label fw-semibold">Form title</label>
                            <input
                                className="form-control"
                                style={{ maxWidth: 480 }}
                                value={schema.title}
                                onChange={(e) =>
                                    setSchema({ ...schema, title: e.target.value })
                                }
                            />
                        </div>
                    </div>

                    {schema.sections.map((section, sectionIndex) => (
                        <div className="card mb-3" key={section.id ?? sectionIndex}>
                            <div className="card-header bg-white fw-semibold">
                                <i className="bi bi-collection me-2 text-secondary" />
                                {section.title || `Section ${sectionIndex + 1}`}
                            </div>
                            <div className="table-responsive">
                                <table className="table align-middle mb-0">
                                    <thead>
                                        <tr className="small text-secondary">
                                            <th className="ps-3">Label</th>
                                            <th style={{ width: 170 }}>Detected type</th>
                                            <th style={{ width: 90 }}>Required</th>
                                            <th>Options</th>
                                            <th style={{ width: 60 }} />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {section.fields.map((field, fieldIndex) => (
                                            <tr key={field.id ?? fieldIndex}>
                                                <td className="ps-3">
                                                    <input
                                                        className="form-control form-control-sm"
                                                        value={field.label}
                                                        onChange={(e) =>
                                                            patchField(sectionIndex, fieldIndex, {
                                                                label: e.target.value,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td>
                                                    <select
                                                        className="form-select form-select-sm"
                                                        value={field.type}
                                                        onChange={(e) =>
                                                            patchField(sectionIndex, fieldIndex, {
                                                                type: e.target.value,
                                                            })
                                                        }
                                                    >
                                                        {INPUT_TYPE_OPTIONS.map((t) => (
                                                            <option key={t.type} value={t.type}>
                                                                {t.label}
                                                            </option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="text-center">
                                                    <input
                                                        type="checkbox"
                                                        className="form-check-input"
                                                        checked={field.required}
                                                        onChange={(e) =>
                                                            patchField(sectionIndex, fieldIndex, {
                                                                required: e.target.checked,
                                                            })
                                                        }
                                                    />
                                                </td>
                                                <td className="small text-secondary">
                                                    {(field.options ?? []).length > 0
                                                        ? field.options
                                                              .map((o) => o.label)
                                                              .join(', ')
                                                        : '—'}
                                                </td>
                                                <td className="text-end pe-3">
                                                    <button
                                                        type="button"
                                                        className="btn btn-sm btn-outline-danger border-0"
                                                        title="Drop this field"
                                                        onClick={() =>
                                                            removeField(sectionIndex, fieldIndex)
                                                        }
                                                    >
                                                        <i className="bi bi-trash" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                        {section.fields.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={5}
                                                    className="text-center text-secondary small py-3"
                                                >
                                                    No fields in this section.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}

                    <div className="d-flex justify-content-between">
                        <button
                            type="button"
                            className="btn btn-outline-danger"
                            onClick={() => {
                                setPhase('upload');
                                setSchema(null);
                                setImportInfo(null);
                            }}
                        >
                            Start over
                        </button>
                        <button
                            type="button"
                            className="btn btn-primary"
                            disabled={committing}
                            onClick={commit}
                        >
                            {committing ? (
                                <>
                                    <span className="spinner-border spinner-border-sm me-2" />
                                    Creating…
                                </>
                            ) : (
                                <>
                                    Create form <i className="bi bi-arrow-right ms-1" />
                                </>
                            )}
                        </button>
                    </div>
                </>
            )}
        </AuthenticatedLayout>
    );
}
