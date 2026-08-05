import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const STATUS_BADGES = {
    draft: 'text-bg-secondary',
    published: 'text-bg-success',
    archived: 'text-bg-warning',
};

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
                <div className="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 className="page-title mb-0">Form Builder</h3>
                    <Link href={route('forms.create')} className="btn btn-primary">
                        <i className="bi bi-plus-lg me-1" />
                        New form
                    </Link>
                </div>
            }
        >
            <Head title="Forms" />

            {flash?.success && (
                <div className="alert alert-success py-2">{flash.success}</div>
            )}

            <div className="card">
                {forms.data.length === 0 ? (
                    <div className="card-body text-center text-secondary py-5">
                        <i className="bi bi-ui-checks fs-1 d-block mb-3" />
                        <p className="mb-3">You don't have any forms yet.</p>
                        <Link href={route('forms.create')} className="btn btn-primary">
                            Create your first form
                        </Link>
                    </div>
                ) : (
                    <div className="table-responsive">
                        <table className="table table-hover align-middle mb-0">
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
                                            >
                                                {form.title}
                                            </Link>
                                            <div className="small text-secondary font-monospace">
                                                /f/{form.public_id}
                                            </div>
                                        </td>
                                        <td>
                                            <span
                                                className={
                                                    'badge ' +
                                                    (STATUS_BADGES[form.status] ??
                                                        'text-bg-secondary')
                                                }
                                            >
                                                {form.status}
                                            </span>
                                        </td>
                                        <td className="text-secondary">
                                            v{form.current_version}
                                        </td>
                                        <td>
                                            <a
                                                href={`/forms/${form.id}/submissions`}
                                                className="text-decoration-none"
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
