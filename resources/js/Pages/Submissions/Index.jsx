import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ form, columns, submissions, search }) {
    const [query, setQuery] = useState(search ?? '');

    const runSearch = (e) => {
        e.preventDefault();
        router.get(
            route('forms.submissions.index', form.id),
            query ? { q: query } : {},
            { preserveState: true },
        );
    };

    const fileFor = (submission, key) =>
        submission.files.find((f) => f.field_key === key);

    const renderValue = (submission, column) => {
        if (column.type === 'file') {
            const file = fileFor(submission, column.key);
            return file ? (
                <a href={route('submissions.file', file.id)} className="text-decoration-none">
                    <i className="bi bi-paperclip me-1" />
                    {file.original_name}
                </a>
            ) : (
                <span className="text-secondary">—</span>
            );
        }

        const value = submission.data[column.key];

        if (value === null || value === undefined || value === '' || (Array.isArray(value) && !value.length)) {
            return <span className="text-secondary">—</span>;
        }

        if (Array.isArray(value)) return value.join(', ');
        if (column.type === 'rating') return `${value} ★`;

        return String(value);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 className="page-title mb-0">Submissions</h3>
                        <div className="text-secondary small">
                            {form.title} ·{' '}
                            <a href={form.public_url} target="_blank" rel="noreferrer">
                                public form
                            </a>
                        </div>
                    </div>
                    <div className="d-flex gap-2">
                        <a
                            href={route('forms.submissions.export', form.id)}
                            className="btn btn-outline-primary"
                        >
                            <i className="bi bi-download me-1" />
                            Export CSV
                        </a>
                        <Link
                            href={route('forms.edit', form.id)}
                            className="btn btn-outline-secondary"
                        >
                            <i className="bi bi-pencil me-1" />
                            Edit form
                        </Link>
                    </div>
                </div>
            }
        >
            <Head title={`Submissions — ${form.title}`} />

            <form className="mb-3" onSubmit={runSearch}>
                <div className="input-group" style={{ maxWidth: 420 }}>
                    <input
                        className="form-control"
                        placeholder="Search answers…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                    />
                    <button className="btn btn-outline-secondary" type="submit">
                        <i className="bi bi-search" />
                    </button>
                    {search && (
                        <Link
                            href={route('forms.submissions.index', form.id)}
                            className="btn btn-outline-secondary"
                        >
                            Clear
                        </Link>
                    )}
                </div>
            </form>

            <div className="card">
                {submissions.data.length === 0 ? (
                    <div className="card-body text-center text-secondary py-5">
                        <i className="bi bi-inbox fs-1 d-block mb-2" />
                        {search
                            ? 'No submissions match your search.'
                            : 'No submissions yet. Share the public link to start collecting.'}
                    </div>
                ) : (
                    <div className="table-responsive">
                        <table className="table table-hover align-middle mb-0">
                            <thead>
                                <tr className="small text-secondary">
                                    <th className="ps-3">#</th>
                                    <th style={{ whiteSpace: 'nowrap' }}>Submitted</th>
                                    {columns.map((column) => (
                                        <th key={column.key} style={{ minWidth: 120 }}>
                                            {column.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {submissions.data.map((submission) => (
                                    <tr key={submission.id}>
                                        <td className="ps-3 text-secondary">
                                            {submission.id}
                                        </td>
                                        <td
                                            className="text-secondary small"
                                            style={{ whiteSpace: 'nowrap' }}
                                        >
                                            {submission.created_at}
                                        </td>
                                        {columns.map((column) => (
                                            <td key={column.key} className="small">
                                                {renderValue(submission, column)}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {submissions.data.length > 0 && submissions.last_page > 1 && (
                <nav className="mt-3 d-flex justify-content-center">
                    <ul className="pagination pagination-sm">
                        {submissions.links.map((link, i) => (
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
