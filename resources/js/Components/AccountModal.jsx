import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';

/**
 * Shared create/edit modal for both Companies (super -> tenant) and Team
 * (tenant -> user) screens — both are the same three-field (name/email/
 * password) shape, just pointed at different routes.
 */
export default function AccountModal({
    show,
    onClose,
    title,
    submitLabel,
    editing, // null = create, otherwise the row being edited {id, name, email}
    postUrl,
    putUrlFor, // (id) => url
}) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
    });

    useEffect(() => {
        if (show) {
            clearErrors();
            setData({
                name: editing?.name ?? '',
                email: editing?.email ?? '',
                password: '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, editing]);

    if (!show) return null;

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { reset(); onClose(); } };

        if (editing) {
            put(putUrlFor(editing.id), options);
        } else {
            post(postUrl, options);
        }
    };

    return (
        <>
            <div
                className="position-fixed top-0 start-0 w-100 h-100"
                style={{ background: 'rgba(27, 24, 54, 0.35)', zIndex: 1040 }}
                onClick={onClose}
            />
            <div
                className="position-fixed top-50 start-50 translate-middle"
                style={{ zIndex: 1050, width: '100%', maxWidth: 440 }}
            >
                <div className="card">
                    <form onSubmit={submit}>
                        <div className="card-body p-4">
                            <h5 className="mb-3">{title}</h5>

                            <div className="mb-3">
                                <InputLabel htmlFor="name" value="Name" required />
                                <TextInput
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    isFocused
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="mb-3">
                                <InputLabel htmlFor="email" value="Email" required />
                                <TextInput
                                    id="email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    required
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="mb-1">
                                <InputLabel htmlFor="password" required={!editing}>
                                    Password{editing && ' (leave blank to keep unchanged)'}
                                </InputLabel>
                                <TextInput
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    required={!editing}
                                    minLength={8}
                                />
                                <InputError message={errors.password} />
                            </div>
                        </div>

                        <div className="card-footer bg-white d-flex justify-content-between py-3">
                            <SecondaryButton onClick={onClose}>Cancel</SecondaryButton>
                            <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
