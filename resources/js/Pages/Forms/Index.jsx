import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Index({ forms }) {
    return (
        <AuthenticatedLayout
            header={<h3 className="page-title">Form Builder</h3>}
        >
            <Head title="Forms" />
            <div className="card">
                <div className="card-body text-center text-secondary py-5">
                    Forms list coming up — builder wizard is the next step.
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
