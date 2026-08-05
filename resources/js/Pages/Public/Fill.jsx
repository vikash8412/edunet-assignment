import { useMemo, useRef, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import FieldInput from '@/Components/Fill/FieldInput';
import { computeVisibility } from '@/lib/conditions';
import { isInput } from '@/lib/fieldTypes';

export default function Fill({ publicId, schema, preview, fillToken, success }) {
    const multiStep = !!schema.settings?.multi_step && schema.sections.length > 1;
    const [step, setStep] = useState(0);
    const startedRef = useRef(false);

    const initialValues = useMemo(() => {
        const values = {};
        for (const section of schema.sections) {
            for (const field of section.fields) {
                if (!isInput(field.type)) continue;
                values[field.key] =
                    field.default ?? (field.type === 'checkbox' ? [] : '');
            }
        }
        return values;
    }, [schema]);

    const { data, setData, post, processing, errors } = useForm({
        ...initialValues,
        _fill_token: fillToken,
        _hp_website: '',
    });

    const visibility = computeVisibility(schema, data);

    const beacon = (payload) => {
        axios.post(`/f/${publicId}/event`, payload).catch(() => {});
    };

    const setValue = (key, value) => {
        if (!startedRef.current) {
            startedRef.current = true;
            beacon({ type: 'start' });
        }
        setData(key, value);
    };

    const submit = (e) => {
        e.preventDefault();
        post(`/f/${publicId}`, {
            forceFormData: true,
            preserveScroll: true,
            onError: (serverErrors) => {
                if (!multiStep) return;

                // Jump to the first step that actually contains an errored
                // field — otherwise the error renders on whichever step the
                // respondent happened to submit from, even if the invalid
                // field lives on an earlier, unmounted step.
                const erroredKeys = Object.keys(serverErrors);
                const stepIndex = schema.sections.findIndex((section) =>
                    section.fields.some((field) => erroredKeys.includes(field.key)),
                );

                if (stepIndex !== -1 && stepIndex !== step) {
                    setStep(stepIndex);
                    window.scrollTo({ top: 0 });
                }
            },
        });
    };

    // Which steps have at least one field currently reported as invalid —
    // drives the small warning dot on the step indicator below.
    const stepsWithErrors = useMemo(() => {
        if (!multiStep) return new Set();
        const erroredKeys = Object.keys(errors).filter((key) => key !== '_form');
        return new Set(
            schema.sections
                .map((section, i) =>
                    section.fields.some((field) => erroredKeys.includes(field.key)) ? i : null,
                )
                .filter((i) => i !== null),
        );
    }, [errors, multiStep, schema]);

    if (success) {
        return (
            <Shell title={schema.title}>
                <div className="card fill-header">
                    <div className="card-body text-center py-5">
                        <div className="text-success fs-1 mb-2">
                            <i className="bi bi-check-circle" />
                        </div>
                        <h4>{schema.settings?.success_message ?? 'Thank you!'}</h4>
                        <p className="text-secondary mb-0">
                            Your response has been recorded.
                        </p>
                    </div>
                </div>
            </Shell>
        );
    }

    const sections = multiStep ? [schema.sections[step]] : schema.sections;
    const isLastStep = !multiStep || step === schema.sections.length - 1;

    // A step is "empty" for the respondent when every field is hidden.
    const sectionVisibleFields = (section) =>
        section.fields.filter(
            (field) => !isInput(field.type) || visibility[field.key] !== false,
        );

    return (
        <Shell title={schema.title}>
            {preview && (
                <div className="alert alert-warning py-2 small">
                    <i className="bi bi-eye me-1" />
                    Draft preview — only you can see this. Publish the form to accept
                    responses.
                </div>
            )}

            <div className="card fill-header mb-3">
                <div className="card-body">
                    <h3 className="mb-1">{schema.title}</h3>
                    {schema.description && (
                        <p className="text-secondary mb-0">{schema.description}</p>
                    )}
                </div>
            </div>

            {multiStep && (
                <div className="mb-3">
                    <div className="d-flex justify-content-between small text-secondary mb-1">
                        <span>
                            Step {step + 1} of {schema.sections.length}
                            {schema.sections[step].title
                                ? ` — ${schema.sections[step].title}`
                                : ''}
                            {stepsWithErrors.has(step) && (
                                <span className="text-danger ms-2">
                                    <i className="bi bi-exclamation-circle-fill me-1" />
                                    fix the errors below
                                </span>
                            )}
                        </span>
                        <span>
                            {Math.round(((step + 1) / schema.sections.length) * 100)}%
                        </span>
                    </div>
                    <div className="progress" style={{ height: 6 }}>
                        <div
                            className="progress-bar"
                            style={{
                                width: `${((step + 1) / schema.sections.length) * 100}%`,
                            }}
                        />
                    </div>
                    {stepsWithErrors.size > 0 && (
                        <div className="d-flex gap-1 mt-1">
                            {schema.sections.map((_, i) => (
                                <span
                                    key={i}
                                    className={
                                        'rounded-circle ' +
                                        (stepsWithErrors.has(i) ? 'bg-danger' : 'bg-secondary-subtle')
                                    }
                                    style={{ width: 6, height: 6 }}
                                    title={
                                        stepsWithErrors.has(i)
                                            ? `Step ${i + 1} has errors`
                                            : undefined
                                    }
                                />
                            ))}
                        </div>
                    )}
                </div>
            )}

            {errors._form && (
                <div className="alert alert-danger py-2 small">{errors._form}</div>
            )}

            <form onSubmit={submit}>
                {/* Honeypot: humans never see it, bots fill it. */}
                <div className="hp-field" aria-hidden="true">
                    <label>
                        Website
                        <input
                            type="text"
                            tabIndex={-1}
                            autoComplete="off"
                            value={data._hp_website}
                            onChange={(e) => setData('_hp_website', e.target.value)}
                        />
                    </label>
                </div>

                {sections.map((section) => (
                    <div className="card mb-3" key={section.id}>
                        <div className="card-body">
                            {!multiStep && (section.title || section.description) && (
                                <div className="mb-3 border-bottom pb-2">
                                    {section.title && (
                                        <h5 className="mb-1">{section.title}</h5>
                                    )}
                                    {section.description && (
                                        <p className="text-secondary small mb-0">
                                            {section.description}
                                        </p>
                                    )}
                                </div>
                            )}

                            {sectionVisibleFields(section).length === 0 && (
                                <p className="text-secondary small mb-0">
                                    Nothing to fill in here based on your answers.
                                </p>
                            )}

                            {section.fields.map((field) => {
                                if (
                                    isInput(field.type) &&
                                    visibility[field.key] === false
                                ) {
                                    return null;
                                }
                                return (
                                    <div className="mb-3" key={field.id}>
                                        <FieldInput
                                            field={field}
                                            value={data[field.key]}
                                            error={errors[field.key]}
                                            onChange={(value) =>
                                                setValue(field.key, value)
                                            }
                                        />
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                ))}

                <div className="d-flex justify-content-between">
                    {multiStep && step > 0 ? (
                        <button
                            type="button"
                            className="btn btn-outline-secondary"
                            onClick={() => {
                                setStep(step - 1);
                                window.scrollTo({ top: 0 });
                            }}
                        >
                            <i className="bi bi-arrow-left me-1" /> Back
                        </button>
                    ) : (
                        <span />
                    )}

                    {isLastStep ? (
                        <button
                            type="submit"
                            className="btn btn-primary px-4"
                            disabled={processing || preview}
                        >
                            {processing ? (
                                <>
                                    <span className="spinner-border spinner-border-sm me-2" />
                                    Submitting…
                                </>
                            ) : (
                                schema.settings?.submit_label ?? 'Submit'
                            )}
                        </button>
                    ) : (
                        <button
                            type="button"
                            className="btn btn-primary"
                            onClick={() => {
                                const next = step + 1;
                                setStep(next);
                                beacon({ type: 'step', step: next });
                                window.scrollTo({ top: 0 });
                            }}
                        >
                            Next <i className="bi bi-arrow-right ms-1" />
                        </button>
                    )}
                </div>
            </form>
        </Shell>
    );
}

function Shell({ title, children }) {
    return (
        <div className="py-4 py-md-5 px-3">
            <Head title={title} />
            <div className="fill-shell mx-auto">{children}</div>
            <div className="text-center text-secondary small mt-4">
                Powered by AI Form Builder
            </div>
        </div>
    );
}
