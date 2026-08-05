import { useEffect, useMemo, useReducer, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Stepper from '@/Components/Builder/Stepper';
import GeneratePanel from '@/Components/Ai/GeneratePanel';
import DetailsStep from './Steps/DetailsStep';
import BuilderStep from './Steps/BuilderStep';
import SettingsStep from './Steps/SettingsStep';
import FinishStep from './Steps/FinishStep';
import { Head, router, usePage } from '@inertiajs/react';
import { emptySchema } from '@/lib/fieldTypes';
import { schemaReducer } from '@/lib/schemaStore';
import { HANDOFF_KEY } from '@/Pages/Ai/Generate';

const STEP_ORDER = ['details', 'builder', 'settings', 'finish'];

/** Picks up (and clears) a schema handed off from the standalone AI-generate page. */
function readAiHandoff() {
    try {
        const raw = sessionStorage.getItem(HANDOFF_KEY);
        if (!raw) return null;
        sessionStorage.removeItem(HANDOFF_KEY);
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

export default function Wizard({ mode, form, initialStep }) {
    const { errors } = usePage().props;
    const isAiHandoff = mode === 'create' && new URLSearchParams(window.location.search).get('from') === 'ai';

    const [schema, dispatch] = useReducer(
        schemaReducer,
        undefined,
        () => (isAiHandoff && readAiHandoff()) || form?.schema || emptySchema(),
    );
    const [status, setStatus] = useState(form?.status ?? 'draft');
    const [step, setStep] = useState(() => {
        if (isAiHandoff) return 'builder';
        return STEP_ORDER.includes(initialStep) && (form || initialStep === 'details')
            ? initialStep
            : 'details';
    });
    const [maxReached, setMaxReached] = useState(() => {
        if (mode === 'edit') return 'finish';
        return isAiHandoff ? 'builder' : step;
    });
    const [saving, setSaving] = useState(false);

    const serverErrors = useMemo(
        () => (errors?.schema ? String(errors.schema).split('\n').filter(Boolean) : []),
        [errors],
    );

    // Inertia re-renders this component in place after store/update redirects
    // (create -> edit is the same page component), so sync the step from props.
    useEffect(() => {
        if (STEP_ORDER.includes(initialStep) && form) {
            setStep(initialStep);
            setMaxReached('finish');
        }
    }, [initialStep, form?.id]);

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
            header={
                <div>
                    <span className="page-eyebrow d-block mb-1">
                        {mode === 'edit' ? 'Editing' : 'New form'}
                    </span>
                    <h3 className="page-title mb-0">{schema.title || 'Untitled form'}</h3>
                </div>
            }
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
                    aiPanel={
                        // AI-assisted editing lives here only for a form that
                        // already exists — creating from a prompt happens on
                        // its own page (Forms index → "Generate with AI").
                        mode === 'edit' ? (
                            <GeneratePanel
                                schema={schema}
                                formId={form.id}
                                onApply={(generated) =>
                                    dispatch({ type: 'replace', schema: generated })
                                }
                            />
                        ) : null
                    }
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
