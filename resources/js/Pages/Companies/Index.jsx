import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AccountModal from '@/Components/AccountModal';

export default function Index({ companies }) {
    const { flash } = usePage().props;
    const [modal, setModal] = useState(null); // null | 'create' | {id,name,email}

    const disable = (company) => {
        if (confirm(`Disable "${company.name}"? Their data is kept, but logins and public forms will stop working until restored.`)) {
            router.delete(route('companies.destroy', company.id));
        }
    };

    const restore = (company) => {
        router.post(route('companies.restore', company.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-end flex-wrap gap-3">
                    <div>
                        <span className="page-eyebrow d-block mb-1">Platform</span>
                        <h3 className="page-title mb-0">Companies</h3>
                    </div>
                    <button type="button" className="btn btn-primary" onClick={() => setModal('create')}>
                        <i className="bi bi-plus-lg me-1" />
                        New company
                    </button>
                </div>
            }
        >
            <Head title="Companies" />

            {flash?.success && <div className="alert alert-success py-2">{flash.success}</div>}

            <div className="card">
                {companies.data.length === 0 ? (
                    <div className="card-body text-center text-secondary py-5">
                        <i className="bi bi-buildings fs-1 d-block mb-3" />
                        <p className="mb-3">No companies yet.</p>
                        <button type="button" className="btn btn-primary" onClick={() => setModal('create')}>
                            Create the first company
                        </button>
                    </div>
                ) : (
                    <div className="table-responsive">
                        <table className="table table-hover align-middle mb-0 forms-table">
                            <thead>
                                <tr className="small text-secondary">
                                    <th className="ps-3">Company</th>
                                    <th>Status</th>
                                    <th>Team members</th>
                                    <th>Forms</th>
                                    <th>Created</th>
                                    <th className="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {companies.data.map((company) => (
                                    <tr key={company.id}>
                                        <td className="ps-3">
                                            <div className="fw-semibold" style={{ color: 'var(--ink)' }}>
                                                {company.name}
                                            </div>
                                            <div className="small text-secondary">{company.email}</div>
                                        </td>
                                        <td>
                                            <span
                                                className={
                                                    'status-pill ' +
                                                    (company.disabled ? 'status-archived' : 'status-published')
                                                }
                                            >
                                                {company.disabled ? 'Disabled' : 'Active'}
                                            </span>
                                        </td>
                                        <td className="text-secondary">{company.team_members_count}</td>
                                        <td className="text-secondary">{company.forms_count}</td>
                                        <td className="text-secondary small">{company.created_at}</td>
                                        <td className="text-end pe-3">
                                            <div className="btn-group btn-group-sm">
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-secondary"
                                                    title="Edit"
                                                    onClick={() => setModal(company)}
                                                >
                                                    <i className="bi bi-pencil" />
                                                </button>
                                                {company.disabled ? (
                                                    <button
                                                        type="button"
                                                        className="btn btn-outline-secondary"
                                                        title="Restore"
                                                        onClick={() => restore(company)}
                                                    >
                                                        <i className="bi bi-arrow-counterclockwise" />
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        className="btn btn-outline-danger"
                                                        title="Disable"
                                                        onClick={() => disable(company)}
                                                    >
                                                        <i className="bi bi-slash-circle" />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {companies.data.length > 0 && companies.last_page > 1 && (
                <nav className="mt-3 d-flex justify-content-center">
                    <ul className="pagination pagination-sm">
                        {companies.links.map((link, i) => (
                            <li
                                key={i}
                                className={
                                    'page-item ' + (link.active ? 'active ' : '') + (!link.url ? 'disabled' : '')
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

            <AccountModal
                show={modal !== null}
                onClose={() => setModal(null)}
                title={modal === 'create' ? 'New company' : 'Edit company'}
                submitLabel={modal === 'create' ? 'Create company' : 'Save changes'}
                editing={modal === 'create' ? null : modal}
                postUrl={route('companies.store')}
                putUrlFor={(id) => route('companies.update', id)}
            />
        </AuthenticatedLayout>
    );
}
