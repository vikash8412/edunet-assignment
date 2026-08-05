import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <p className="small text-secondary">
                Thanks for signing up! Please verify your email address by
                clicking the link we just emailed to you. If you didn't receive
                the email, we will gladly send you another.
            </p>

            {status === 'verification-link-sent' && (
                <div className="alert alert-success py-2 small">
                    A new verification link has been sent to your email address.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="d-flex align-items-center justify-content-between">
                    <PrimaryButton disabled={processing}>
                        Resend verification email
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="btn btn-link small text-decoration-none"
                    >
                        Log out
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
