import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Index({ forms }) {
    const { flash } = usePage().props;

    const destroy = (form) => {
        if (confirm(`Delete "${form.title}" and all of its submissions?`)) {
            router.delete(route('forms.destroy', form.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-end flex-wrap gap-3">
                    <div>
                        <span className="page-eyebrow d-block mb-1">Workspace</span>
                        <h3 className="page-title mb-0">Your forms</h3>
                    </div>
                    <div className="d-flex gap-2">
                        <Link
                            href={route('imports.create')}
                            className="btn btn-outline-secondary"
                        >
                            <i className="bi bi-file-earmark-arrow-up me-1" />
                            Import
                        </Link>
                        <Link href={route('ai.generate')} className="btn btn-ai">
                            <i className="bi bi-stars me-1" />
                            Generate with AI
                        </Link>
                        <Link href={route('forms.create')} className="btn btn-primary">
                            <i className="bi bi-plus-lg me-1" />
                            New form
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title="Your forms" />

            {flash?.success && (
                <div className="alert alert-success py-2">{flash.success}</div>
            )}

            <div className="card">
                {forms.data.length === 0 ? (
                    <div className="card-body text-center py-5">
                        <div
                            className="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                            style={{
                                width: 56,
                                height: 56,
                                background: 'var(--accent-soft)',
                                color: 'var(--accent)',
                            }}
                        >
                            <i className="bi bi-stars fs-4" />
                        </div>
                        <p className="text-secondary mb-3">
                            No forms yet — describe one to the AI, import a
                            document, or start from a blank canvas.
                        </p>
                        <div className="d-flex gap-2 justify-content-center flex-wrap">
                            <Link href={route('ai.generate')} className="btn btn-ai">
                                <i className="bi bi-stars me-1" />
                                Generate with AI
                            </Link>
                            <Link href={route('forms.create')} className="btn btn-primary">
                                Create your first form
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="table-responsive">
                        <table className="table table-hover align-middle mb-0 forms-table">
                            <thead>
                                <tr className="small text-secondary">
                                    <th className="ps-3">Form</th>
                                    <th>Status</th>
                                    <th>Version</th>
                                    <th>Submissions</th>
                                    <th>Updated</th>
                                    <th className="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {forms.data.map((form) => (
                                    <tr key={form.id}>
                                        <td className="ps-3">
                                            <Link
                                                href={route('forms.edit', form.id)}
                                                className="fw-semibold text-decoration-none"
                                                style={{ color: 'var(--ink)' }}
                                            >
                                                {form.title}
                                            </Link>
                                            <div className="small text-secondary font-monospace">
                                                /f/{form.public_id}
                                            </div>
                                        </td>
                                        <td>
                                            <span className={'status-pill status-' + form.status}>
                                                {form.status}
                                            </span>
                                        </td>
                                        <td className="text-secondary">
                                            v{form.current_version}
                                        </td>
                                        <td>
                                            <a
                                                href={`/forms/${form.id}/submissions`}
                                                className="text-decoration-none fw-semibold"
                                                style={{ color: 'var(--accent)' }}
                                            >
                                                {form.submissions_count}
                                            </a>
                                        </td>
                                        <td className="text-secondary small">
                                            {form.updated_at}
                                        </td>
                                        <td className="text-end pe-3">
                                            <div className="btn-group btn-group-sm">
                                                <a
                                                    href={form.public_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="btn btn-outline-secondary"
                                                    title="Open public form"
                                                >
                                                    <i className="bi bi-box-arrow-up-right" />
                                                </a>
                                                <Link
                                                    href={route('forms.edit', form.id)}
                                                    className="btn btn-outline-secondary"
                                                    title="Edit"
                                                >
                                                    <i className="bi bi-pencil" />
                                                </Link>
                                                <Link
                                                    href={route('forms.analytics', form.id)}
                                                    className="btn btn-outline-secondary"
                                                    title="Analytics"
                                                >
                                                    <i className="bi bi-graph-up" />
                                                </Link>
                                                <Link
                                                    href={route('forms.versions.index', form.id)}
                                                    className="btn btn-outline-secondary"
                                                    title="Version history"
                                                >
                                                    <i className="bi bi-clock-history" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-danger"
                                                    title="Delete"
                                                    onClick={() => destroy(form)}
                                                >
                                                    <i className="bi bi-trash" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {forms.data.length > 0 && forms.last_page > 1 && (
                <nav className="mt-3 d-flex justify-content-center">
                    <ul className="pagination pagination-sm">
                        {forms.links.map((link, i) => (
                            <li
                                key={i}
                                className={
                                    'page-item ' +
                                    (link.active ? 'active ' : '') +
                                    (!link.url ? 'disabled' : '')
                                }
                            >
                                <Link
                                    className="page-link"
                                    href={link.url ?? '#'}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            </li>
                        ))}
                    </ul>
                </nav>
            )}
        </AuthenticatedLayout>
    );
}
