import { useMemo, useReducer, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Stepper from '@/Components/Builder/Stepper';
import DetailsStep from './Steps/DetailsStep';
import BuilderStep from './Steps/BuilderStep';
import SettingsStep from './Steps/SettingsStep';
import FinishStep from './Steps/FinishStep';
import { Head, router, usePage } from '@inertiajs/react';
import { emptySchema } from '@/lib/fieldTypes';
import { schemaReducer } from '@/lib/schemaStore';

const STEP_ORDER = ['details', 'builder', 'settings', 'finish'];

export default function Wizard({ mode, form, initialStep }) {
    const { errors } = usePage().props;

    const [schema, dispatch] = useReducer(
        schemaReducer,
        undefined,
        () => form?.schema ?? emptySchema(),
    );
    const [status, setStatus] = useState(form?.status ?? 'draft');
    const [step, setStep] = useState(
        STEP_ORDER.includes(initialStep) && (form || initialStep === 'details')
            ? initialStep
            : 'details',
    );
    const [maxReached, setMaxReached] = useState(mode === 'edit' ? 'finish' : step);
    const [saving, setSaving] = useState(false);

    const serverErrors = useMemo(
        () => (errors?.schema ? String(errors.schema).split('\n').filter(Boolean) : []),
        [errors],
    );

    const goTo = (target) => {
        setStep(target);
        if (STEP_ORDER.indexOf(target) > STEP_ORDER.indexOf(maxReached)) {
            setMaxReached(target);
        }
        window.scrollTo({ top: 0 });
    };

    const publicUrlPreview = form
        ? form.public_url
        : `${window.location.origin}/f/{generated-on-save}`;

    const save = () => {
        setSaving(true);
        const payload = { schema, status, return_step: 'finish' };
        const options = {
            preserveScroll: true,
            onFinish: () => setSaving(false),
            onError: () => goTo('builder'),
        };

        if (mode === 'edit') {
            router.put(route('forms.update', form.id), payload, options);
        } else {
            router.post(route('forms.store'), payload, options);
        }
    };

    const cancel = () => router.visit(route('forms.index'));

    return (
        <AuthenticatedLayout
            header={<h3 className="page-title">Form Builder</h3>}
        >
            <Head title={form ? `Edit — ${form.title}` : 'New form'} />

            <Stepper current={step} maxReached={maxReached} onNavigate={goTo} />

            {step === 'details' && (
                <DetailsStep
                    schema={schema}
                    dispatch={dispatch}
                    publicUrlPreview={publicUrlPreview}
                    onNext={() => goTo('builder')}
                    onCancel={cancel}
                />
            )}

            {step === 'builder' && (
                <BuilderStep
                    schema={schema}
                    dispatch={dispatch}
                    serverErrors={serverErrors}
                    aiPanel={null}
                    onBack={() => goTo('details')}
                    onNext={() => goTo('settings')}
                    onCancel={cancel}
                />
            )}

            {step === 'settings' && (
                <SettingsStep
                    schema={schema}
                    dispatch={dispatch}
                    status={status}
                    setStatus={setStatus}
                    saving={saving}
                    onBack={() => goTo('builder')}
                    onSave={save}
                    onCancel={cancel}
                />
            )}

            {step === 'finish' &&
                (form ? (
                    <FinishStep form={form} status={status} />
                ) : (
                    <div className="card">
                        <div className="card-body text-center text-secondary py-5">
                            Save the form first to get its public link.
                        </div>
                    </div>
                ))}
        </AuthenticatedLayout>
    );
}
