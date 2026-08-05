import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const SOURCE_META = {
    builder: { icon: 'bi-pencil-square', label: 'Builder edit' },
    ai: { icon: 'bi-stars', label: 'AI generated' },
    import: { icon: 'bi-file-earmark-arrow-up', label: 'Imported' },
    rollback: { icon: 'bi-arrow-counterclockwise', label: 'Rollback' },
};

function DiffSummary({ diff }) {
    if (!diff) {
        return <span className="text-secondary small">Initial version</span>;
    }

    const total =
        diff.added.length + diff.removed.length + diff.changed.length;

    if (total === 0 && !diff.title_changed) {
        return <span className="text-secondary small">No field changes</span>;
    }

    return (
        <div className="small">
            {diff.title_changed && (
                <span className="badge diff-changed text-dark me-1">title renamed</span>
            )}
            {diff.added.map((field) => (
                <span key={'a' + field.key} className="badge diff-added text-dark me-1">
                    + {field.label}
                </span>
            ))}
            {diff.removed.map((field) => (
                <span key={'r' + field.key} className="badge diff-removed text-dark me-1">
                    − {field.label}
                </span>
            ))}
            {diff.changed.map((field) => (
                <span
                    key={'c' + field.key}
                    className="badge diff-changed text-dark me-1"
                    title={'Changed: ' + field.props.join(', ')}
                >
                    ~ {field.label} ({field.props.join(', ')})
                </span>
            ))}
        </div>
    );
}

export default function Versions({ form, versions }) {
    const { flash } = usePage().props;

    const rollback = (version) => {
        if (
            confirm(
                `Roll back to version ${version.version}? The current schema stays in history; the old snapshot becomes a new version.`,
            )
        ) {
            router.post(route('forms.versions.rollback', [form.id, version.id]));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 className="page-title mb-0">Version history</h3>
                        <div className="text-secondary small">{form.title}</div>
                    </div>
                    <Link
                        href={route('forms.edit', form.id)}
                        className="btn btn-outline-secondary"
                    >
                        <i className="bi bi-pencil me-1" />
                        Edit form
                    </Link>
                </div>
            }
        >
            <Head title={`Versions — ${form.title}`} />

            {flash?.success && (
                <div className="alert alert-success py-2">{flash.success}</div>
            )}

            <div className="card">
                <div className="list-group list-group-flush">
                    {versions.map((version) => {
                        const source = SOURCE_META[version.source] ?? SOURCE_META.builder;

                        return (
                            <div className="list-group-item py-3" key={version.id}>
                                <div className="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <div className="min-w-0">
                                        <div className="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                            <strong>v{version.version}</strong>
                                            {version.is_current && (
                                                <span className="badge text-bg-primary">current</span>
                                            )}
                                            <span className="badge text-bg-light border">
                                                <i className={`bi ${source.icon} me-1`} />
                                                {source.label}
                                            </span>
                                            {version.label && (
                                                <span className="text-secondary small">
                                                    “{version.label}”
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-secondary small mb-1">
                                            {version.created_at}
                                            {version.author && <> · {version.author}</>} ·{' '}
                                            {version.field_count} field(s)
                                        </div>
                                        <DiffSummary diff={version.diff} />
                                    </div>

                                    {!version.is_current && (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-primary"
                                            onClick={() => rollback(version)}
                                        >
                                            <i className="bi bi-arrow-counterclockwise me-1" />
                                            Rollback
                                        </button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
