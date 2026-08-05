import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import DangerButton from '@/Components/DangerButton';
import TextInput from '@/Components/TextInput';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

function ProfileInformation({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({ name: user.name, email: user.email });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <div className="card mb-4">
            <div className="card-body">
                <h5>Profile information</h5>
                <form onSubmit={submit} className="mt-3" style={{ maxWidth: 480 }}>
                    <div className="mb-3">
                        <InputLabel htmlFor="name" value="Name" />
                        <TextInput
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            autoComplete="name"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="mb-3">
                        <InputLabel htmlFor="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            autoComplete="username"
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="d-flex align-items-center gap-3">
                        <PrimaryButton disabled={processing}>Save</PrimaryButton>
                        {recentlySuccessful && (
                            <span className="text-success small">Saved.</span>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}

function UpdatePassword() {
    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: () => reset('password', 'password_confirmation', 'current_password'),
        });
    };

    return (
        <div className="card mb-4">
            <div className="card-body">
                <h5>Update password</h5>
                <form onSubmit={submit} className="mt-3" style={{ maxWidth: 480 }}>
                    <div className="mb-3">
                        <InputLabel htmlFor="current_password" value="Current password" />
                        <TextInput
                            id="current_password"
                            type="password"
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                            autoComplete="current-password"
                        />
                        <InputError message={errors.current_password} />
                    </div>
                    <div className="mb-3">
                        <InputLabel htmlFor="password" value="New password" />
                        <TextInput
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="mb-3">
                        <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>
                    <div className="d-flex align-items-center gap-3">
                        <PrimaryButton disabled={processing}>Save</PrimaryButton>
                        {recentlySuccessful && (
                            <span className="text-success small">Saved.</span>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}

function DeleteAccount() {
    const [confirming, setConfirming] = useState(false);
    const { data, setData, delete: destroy, processing, reset, errors } =
        useForm({ password: '' });

    const submit = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onFinish: () => reset(),
        });
    };

    return (
        <div className="card border-danger-subtle">
            <div className="card-body">
                <h5 className="text-danger">Delete account</h5>
                <p className="small text-secondary">
                    Once your account is deleted, all of its resources and data
                    will be permanently deleted.
                </p>
                {!confirming ? (
                    <DangerButton onClick={() => setConfirming(true)}>
                        Delete account
                    </DangerButton>
                ) : (
                    <form onSubmit={submit} style={{ maxWidth: 480 }}>
                        <div className="mb-3">
                            <InputLabel htmlFor="delete_password" value="Confirm your password" />
                            <TextInput
                                id="delete_password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                isFocused
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="d-flex gap-2">
                            <DangerButton disabled={processing}>
                                Permanently delete
                            </DangerButton>
                            <button
                                type="button"
                                className="btn btn-outline-secondary"
                                onClick={() => setConfirming(false)}
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}
            </div>
        </div>
    );
}

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout
            header={<h3 className="page-title">Profile</h3>}
        >
            <Head title="Profile" />
            <ProfileInformation mustVerifyEmail={mustVerifyEmail} status={status} />
            <UpdatePassword />
            <DeleteAccount />
        </AuthenticatedLayout>
    );
}
