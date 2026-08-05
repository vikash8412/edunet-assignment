import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="Welcome" />
            <div className="min-vh-100 d-flex flex-column align-items-center justify-content-center text-center px-3">
                <ApplicationLogo className="fs-2 mb-3" />
                <p className="text-secondary mb-4" style={{ maxWidth: 520 }}>
                    Build data-collection forms by hand, generate them with AI
                    from a plain-language prompt, or import them from Word and
                    Excel documents — then share a public link and collect
                    submissions.
                </p>
                <div className="d-flex gap-2">
                    {auth.user ? (
                        <Link
                            href={route(auth.user.role === 'super' ? 'companies.index' : 'forms.index')}
                            className="btn btn-primary"
                        >
                            {auth.user.role === 'super' ? 'Go to companies' : 'Go to my forms'}
                        </Link>
                    ) : (
                        <Link href={route('login')} className="btn btn-primary">
                            Log in
                        </Link>
                    )}
                </div>
            </div>
        </>
    );
}
