import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="min-vh-100 d-flex flex-column align-items-center justify-content-center py-5">
            <Link href="/" className="text-decoration-none mb-4">
                <ApplicationLogo className="fs-3" />
            </Link>

            <div className="card border-0 shadow-sm" style={{ width: '100%', maxWidth: 420 }}>
                <div className="card-body p-4">{children}</div>
            </div>
        </div>
    );
}
