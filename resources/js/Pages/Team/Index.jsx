import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AccountModal from '@/Components/AccountModal';

export default function Index({ members }) {
    const { flash } = usePage().props;
    const [modal, setModal] = useState(null); // null | 'create' | {id,name,email}

    const remove = (member) => {
        if (confirm(`Remove "${member.name}" from your team? They will no longer be able to log in.`)) {
            router.delete(route('team.destroy', member.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="d-flex justify-content-between align-items-end flex-wrap gap-3">
                    <div>
                        <span className="page-eyebrow d-block mb-1">Workspace</span>
                        <h3 className="page-title mb-0">Your team</h3>
                    </div>
                    <button type="button" className="btn btn-primary" onClick={() => setModal('create')}>
                        <i className="bi bi-plus-lg me-1" />
                        Add team member
                    </button>
                </div>
            }
        >
            <Head title="Team" />

            {flash?.success && <div className="alert alert-success py-2">{flash.success}</div>}

            <div className="card">
                {members.data.length === 0 ? (
                    <div className="card-body text-center text-secondary py-5">
                        <i className="bi bi-people fs-1 d-block mb-3" />
                        <p className="mb-3">
                            No teammates yet — invite one to share this workspace's forms.
                        </p>
                        <button type="button" className="btn btn-primary" onClick={() => setModal('create')}>
                            Add your first team member
                        </button>
                    </div>
                ) : (
                    <div className="table-responsive">
                        <table className="table table-hover align-middle mb-0 forms-table">
                            <thead>
                                <tr className="small text-secondary">
                                    <th className="ps-3">Name</th>
                                    <th>Email</th>
                                    <th>Added</th>
                                    <th className="text-end pe-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {members.data.map((member) => (
                                    <tr key={member.id}>
                                        <td className="ps-3 fw-semibold" style={{ color: 'var(--ink)' }}>
                                            {member.name}
                                        </td>
                                        <td className="text-secondary">{member.email}</td>
                                        <td className="text-secondary small">{member.created_at}</td>
                                        <td className="text-end pe-3">
                                            <div className="btn-group btn-group-sm">
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-secondary"
                                                    title="Edit"
                                                    onClick={() => setModal(member)}
                                                >
                                                    <i className="bi bi-pencil" />
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-outline-danger"
                                                    title="Remove"
                                                    onClick={() => remove(member)}
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

            {members.data.length > 0 && members.last_page > 1 && (
                <nav className="mt-3 d-flex justify-content-center">
                    <ul className="pagination pagination-sm">
                        {members.links.map((link, i) => (
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
                title={modal === 'create' ? 'Add team member' : 'Edit team member'}
                submitLabel={modal === 'create' ? 'Add member' : 'Save changes'}
                editing={modal === 'create' ? null : modal}
                postUrl={route('team.store')}
                putUrlFor={(id) => route('team.update', id)}
            />
        </AuthenticatedLayout>
    );
}
