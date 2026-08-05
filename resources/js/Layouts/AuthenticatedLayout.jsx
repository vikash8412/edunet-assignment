import { useState } from 'react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';

function TopNavLink({ href, active, children }) {
    return (
        <Link
            href={href}
            className="nav-link px-3 fw-semibold"
            style={{ color: active ? 'var(--accent)' : 'var(--ink-soft)' }}
        >
            {children}
        </Link>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [menuOpen, setMenuOpen] = useState(false);
    const [userOpen, setUserOpen] = useState(false);

    const homeRoute = user.role === 'super' ? 'companies.index' : 'forms.index';

    const links =
        user.role === 'super'
            ? [{ href: route('companies.index'), label: 'Companies', active: route().current('companies.*') }]
            : user.role === 'tenant'
              ? [
                    { href: route('forms.index'), label: 'Forms', active: route().current('forms.*') },
                    { href: route('team.index'), label: 'Team', active: route().current('team.*') },
                ]
              : [{ href: route('forms.index'), label: 'Forms', active: route().current('forms.*') }];

    return (
        <div className="min-vh-100 d-flex flex-column">
            <nav className="app-topbar navbar navbar-expand-md px-3">
                <div className="d-flex align-items-center gap-2">
                    <button
                        className="btn btn-link text-secondary d-md-none p-1"
                        onClick={() => setMenuOpen(!menuOpen)}
                        aria-label="Toggle navigation"
                    >
                        <i className="bi bi-list fs-4" />
                    </button>
                    <Link href={route(homeRoute)} className="text-decoration-none">
                        <ApplicationLogo />
                    </Link>
                    <div className="d-none d-md-flex ms-4">
                        {links.map((l) => (
                            <TopNavLink key={l.label} href={l.href} active={l.active}>
                                {l.label}
                            </TopNavLink>
                        ))}
                    </div>
                </div>

                <div className="ms-auto position-relative">
                    <button
                        className="btn btn-link text-decoration-none text-secondary fw-semibold d-flex align-items-center gap-2"
                        onClick={() => setUserOpen(!userOpen)}
                    >
                        <i className="bi bi-person-circle fs-5" />
                        <span className="d-none d-sm-inline">{user.name}</span>
                        <i className="bi bi-chevron-down small" />
                    </button>
                    {userOpen && (
                        <>
                            <div
                                className="position-fixed top-0 start-0 w-100 h-100"
                                style={{ zIndex: 1029 }}
                                onClick={() => setUserOpen(false)}
                            />
                            <div
                                className="dropdown-menu dropdown-menu-end show position-absolute end-0 mt-1"
                                style={{ zIndex: 1030 }}
                            >
                                <Link className="dropdown-item" href={route('profile.edit')}>
                                    <i className="bi bi-person me-2" />
                                    Profile
                                </Link>
                                <Link
                                    className="dropdown-item"
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                >
                                    <i className="bi bi-box-arrow-right me-2" />
                                    Log out
                                </Link>
                            </div>
                        </>
                    )}
                </div>
            </nav>

            {menuOpen && (
                <div className="app-topbar border-bottom d-md-none px-3 py-2">
                    {links.map((l) => (
                        <TopNavLink key={l.label} href={l.href} active={l.active}>
                            {l.label}
                        </TopNavLink>
                    ))}
                </div>
            )}

            {header && (
                <header className="container-xl mt-4 px-3">{header}</header>
            )}

            <main className="container-xl py-4 px-3 flex-grow-1">{children}</main>
        </div>
    );
}
