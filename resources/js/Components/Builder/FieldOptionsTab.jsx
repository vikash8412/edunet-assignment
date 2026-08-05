import { allInputFields, isChoice, isDisplay, typeMeta } from '@/lib/fieldTypes';

const OPERATORS = [
    { value: 'equals', label: 'equals' },
    { value: 'not_equals', label: 'does not equal' },
    { value: 'contains', label: 'contains' },
    { value: 'greater_than', label: 'is greater than' },
    { value: 'less_than', label: 'is less than' },
    { value: 'is_empty', label: 'is empty' },
    { value: 'is_not_empty', label: 'is not empty' },
];

function Text({ label, value, onChange, ...props }) {
    return (
        <div className="mb-2">
            <label className="form-label small fw-semibold mb-1">{label}</label>
            <input
                className="form-control form-control-sm"
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value === '' ? null : e.target.value)}
                {...props}
            />
        </div>
    );
}

function Num({ label, value, onChange, ...props }) {
    return (
        <div className="col-6 mb-2">
            <label className="form-label small fw-semibold mb-1">{label}</label>
            <input
                type="number"
                className="form-control form-control-sm"
                value={value ?? ''}
                onChange={(e) =>
                    onChange(e.target.value === '' ? null : Number(e.target.value))
                }
                {...props}
            />
        </div>
    );
}

function OptionsManager({ field, patchField }) {
    const options = field.options ?? [];

    const update = (index, prop, value) => {
        const next = options.map((o, i) => (i === index ? { ...o, [prop]: value } : o));
        patchField({ options: next });
    };

    const slug = (label) =>
        label
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') || `option_${options.length + 1}`;

    return (
        <div className="mb-3">
            <label className="form-label small fw-semibold mb-1">Options</label>
            {options.map((option, i) => (
                <div className="d-flex gap-1 mb-1" key={i}>
                    <input
                        className="form-control form-control-sm"
                        placeholder="Label"
                        value={option.label}
                        onChange={(e) => {
                            // Keep value in sync while it still mirrors the label.
                            const auto = slug(option.label) === option.value;
                            update(i, 'label', e.target.value);
                            if (auto) {
                                patchField({
                                    options: options.map((o, j) =>
                                        j === i
                                            ? { ...o, label: e.target.value, value: slug(e.target.value) }
                                            : o,
                                    ),
                                });
                            }
                        }}
                    />
                    <input
                        className="form-control form-control-sm"
                        style={{ maxWidth: 110 }}
                        placeholder="value"
                        value={option.value}
                        onChange={(e) => update(i, 'value', e.target.value)}
                    />
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-danger border-0"
                        onClick={() =>
                            patchField({ options: options.filter((_, j) => j !== i) })
                        }
                    >
                        <i className="bi bi-x-lg" />
                    </button>
                </div>
            ))}
            <button
                type="button"
                className="btn btn-sm btn-outline-primary mt-1"
                onClick={() =>
                    patchField({
                        options: [
                            ...options,
                            {
                                label: `Option ${options.length + 1}`,
                                value: `option_${options.length + 1}`,
                            },
                        ],
                    })
                }
            >
                <i className="bi bi-plus-lg me-1" />
                Add option
            </button>
        </div>
    );
}

function ValidationEditor({ field, patchValidation }) {
    const v = field.validation ?? {};
    const type = field.type;

    return (
        <details className="mb-3" open={Object.keys(v).length > 0}>
            <summary className="small fw-semibold mb-2 cursor-pointer">
                Validation rules
            </summary>

            {(type === 'text' || type === 'textarea') && (
                <>
                    <div className="row g-2">
                        <Num
                            label="Min length"
                            value={v.minLength}
                            min={0}
                            onChange={(x) => patchValidation({ minLength: x })}
                        />
                        <Num
                            label="Max length"
                            value={v.maxLength}
                            min={1}
                            onChange={(x) => patchValidation({ maxLength: x })}
                        />
                    </div>
                    <Text
                        label="Regex pattern"
                        value={v.pattern}
                        placeholder="e.g. ^[A-Z]{2}[0-9]{4}$"
                        onChange={(x) => patchValidation({ pattern: x })}
                    />
                    {type === 'text' && (
                        <div className="form-check form-switch mb-2">
                            <input
                                className="form-check-input"
                                type="checkbox"
                                id={`url-${field.id}`}
                                checked={!!v.url}
                                onChange={(e) =>
                                    patchValidation({ url: e.target.checked || null })
                                }
                            />
                            <label className="form-check-label small" htmlFor={`url-${field.id}`}>
                                Must be a valid URL
                            </label>
                        </div>
                    )}
                </>
            )}

            {type === 'number' && (
                <>
                    <div className="row g-2">
                        <Num label="Min value" value={v.min} onChange={(x) => patchValidation({ min: x })} />
                        <Num label="Max value" value={v.max} onChange={(x) => patchValidation({ max: x })} />
                    </div>
                    <div className="form-check form-switch mb-2">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            id={`int-${field.id}`}
                            checked={!!v.integer}
                            onChange={(e) =>
                                patchValidation({ integer: e.target.checked || null })
                            }
                        />
                        <label className="form-check-label small" htmlFor={`int-${field.id}`}>
                            Whole numbers only
                        </label>
                    </div>
                </>
            )}

            {type === 'phone' && (
                <Text
                    label="Custom pattern (optional)"
                    value={v.pattern}
                    placeholder="Default allows digits, +, -, (), spaces"
                    onChange={(x) => patchValidation({ pattern: x })}
                />
            )}

            {type === 'date' && (
                <div className="row g-2">
                    <div className="col-6 mb-2">
                        <label className="form-label small fw-semibold mb-1">Earliest</label>
                        <input
                            type="date"
                            className="form-control form-control-sm"
                            value={v.minDate ?? ''}
                            onChange={(e) => patchValidation({ minDate: e.target.value || null })}
                        />
                    </div>
                    <div className="col-6 mb-2">
                        <label className="form-label small fw-semibold mb-1">Latest</label>
                        <input
                            type="date"
                            className="form-control form-control-sm"
                            value={v.maxDate ?? ''}
                            onChange={(e) => patchValidation({ maxDate: e.target.value || null })}
                        />
                    </div>
                </div>
            )}

            {type === 'checkbox' && (
                <div className="row g-2">
                    <Num
                        label="Max selections"
                        value={v.max}
                        min={1}
                        onChange={(x) => patchValidation({ max: x })}
                    />
                </div>
            )}

            {type === 'rating' && (
                <div className="row g-2">
                    <Num
                        label="Scale (stars)"
                        value={v.max ?? 5}
                        min={1}
                        max={10}
                        onChange={(x) => patchValidation({ max: x })}
                    />
                </div>
            )}

            {type === 'file' && (
                <>
                    <Text
                        label="Allowed extensions (comma-separated)"
                        value={(v.mimes ?? []).join(', ')}
                        placeholder="pdf, docx, jpg"
                        onChange={(x) =>
                            patchValidation({
                                mimes: x
                                    ? x
                                          .split(',')
                                          .map((m) => m.trim().toLowerCase().replace(/^\./, ''))
                                          .filter(Boolean)
                                    : null,
                            })
                        }
                    />
                    <div className="row g-2">
                        <Num
                            label="Max size (KB)"
                            value={v.maxSizeKb}
                            min={1}
                            onChange={(x) => patchValidation({ maxSizeKb: x })}
                        />
                    </div>
                </>
            )}

            {(type === 'email' || type === 'hidden') && (
                <div className="form-text small">
                    {type === 'email'
                        ? 'Email format is enforced automatically.'
                        : 'Hidden fields accept any text value.'}
                </div>
            )}
        </details>
    );
}

function ConditionsEditor({ field, schema, patchField }) {
    const conditions = field.conditions ?? null;
    const candidates = allInputFields(schema).filter((f) => f.key !== field.key);

    const setRules = (rules, logic = conditions?.logic ?? 'all') => {
        patchField({ conditions: rules.length ? { logic, rules } : null });
    };

    const rules = conditions?.rules ?? [];

    return (
        <details className="mb-3" open={rules.length > 0}>
            <summary className="small fw-semibold mb-2 cursor-pointer">
                Conditional visibility
            </summary>

            {candidates.length === 0 ? (
                <div className="form-text small">
                    Add more fields first — conditions reference other answers.
                </div>
            ) : (
                <>
                    {rules.length > 0 && (
                        <div className="d-flex align-items-center gap-2 mb-2 small">
                            Show this field when
                            <select
                                className="form-select form-select-sm w-auto"
                                value={conditions.logic}
                                onChange={(e) => setRules(rules, e.target.value)}
                            >
                                <option value="all">all</option>
                                <option value="any">any</option>
                            </select>
                            of these match:
                        </div>
                    )}

                    {rules.map((rule, i) => {
                        const needsValue = !['is_empty', 'is_not_empty'].includes(rule.operator);
                        return (
                            <div className="d-flex gap-1 mb-1" key={i}>
                                <select
                                    className="form-select form-select-sm"
                                    value={rule.field}
                                    onChange={(e) =>
                                        setRules(
                                            rules.map((r, j) =>
                                                j === i ? { ...r, field: e.target.value } : r,
                                            ),
                                        )
                                    }
                                >
                                    {candidates.map((f) => (
                                        <option key={f.key} value={f.key}>
                                            {f.label}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className="form-select form-select-sm"
                                    value={rule.operator}
                                    onChange={(e) =>
                                        setRules(
                                            rules.map((r, j) =>
                                                j === i ? { ...r, operator: e.target.value } : r,
                                            ),
                                        )
                                    }
                                >
                                    {OPERATORS.map((o) => (
                                        <option key={o.value} value={o.value}>
                                            {o.label}
                                        </option>
                                    ))}
                                </select>
                                {needsValue && (
                                    <input
                                        className="form-control form-control-sm"
                                        placeholder="value"
                                        value={rule.value ?? ''}
                                        onChange={(e) =>
                                            setRules(
                                                rules.map((r, j) =>
                                                    j === i ? { ...r, value: e.target.value } : r,
                                                ),
                                            )
                                        }
                                    />
                                )}
                                <button
                                    type="button"
                                    className="btn btn-sm btn-outline-danger border-0"
                                    onClick={() => setRules(rules.filter((_, j) => j !== i))}
                                >
                                    <i className="bi bi-x-lg" />
                                </button>
                            </div>
                        );
                    })}

                    <button
                        type="button"
                        className="btn btn-sm btn-outline-primary mt-1"
                        onClick={() =>
                            setRules([
                                ...rules,
                                {
                                    field: candidates[0].key,
                                    operator: 'equals',
                                    value: '',
                                },
                            ])
                        }
                    >
                        <i className="bi bi-plus-lg me-1" />
                        Add condition
                    </button>
                </>
            )}
        </details>
    );
}

export default function FieldOptionsTab({ schema, field, dispatch }) {
    if (!field) {
        return (
            <div className="text-secondary small text-center py-5">
                <i className="bi bi-hand-index fs-3 d-block mb-2" />
                Select a field on the canvas to edit its settings.
            </div>
        );
    }

    const meta = typeMeta(field.type);
    const display = isDisplay(field.type);

    const patchField = (patch) =>
        dispatch({ type: 'updateField', fieldId: field.id, patch });

    const patchValidation = (patch) => {
        const merged = { ...(field.validation ?? {}), ...patch };
        Object.keys(merged).forEach((k) => merged[k] === null && delete merged[k]);
        patchField({ validation: Object.keys(merged).length ? merged : null });
    };

    return (
        <div>
            <div className="d-flex align-items-center gap-2 mb-3">
                <i className={`bi ${meta.icon} text-primary`} />
                <strong className="small">{meta.label}</strong>
            </div>

            <Text
                label={display ? 'Text' : 'Label'}
                value={field.label}
                onChange={(x) => patchField({ label: x ?? '' })}
            />

            {!display && (
                <>
                    <div className="mb-2">
                        <label className="form-label small fw-semibold mb-1">
                            Key
                            <i
                                className="bi bi-info-circle ms-1 text-secondary"
                                title="Identifier used in exports, the API and conditions. Lowercase letters, digits and underscores."
                            />
                        </label>
                        <input
                            className="form-control form-control-sm font-monospace"
                            value={field.key}
                            onChange={(e) =>
                                patchField({
                                    key: e.target.value
                                        .toLowerCase()
                                        .replace(/[^a-z0-9_]/g, '_'),
                                })
                            }
                        />
                    </div>

                    {field.type !== 'hidden' && (
                        <Text
                            label="Placeholder"
                            value={field.placeholder}
                            onChange={(x) => patchField({ placeholder: x })}
                        />
                    )}

                    <Text
                        label="Help text"
                        value={field.help}
                        onChange={(x) => patchField({ help: x })}
                    />

                    {field.type !== 'file' && (
                        <Text
                            label="Default value"
                            value={
                                Array.isArray(field.default)
                                    ? field.default.join(', ')
                                    : field.default
                            }
                            onChange={(x) =>
                                patchField({
                                    default:
                                        field.type === 'checkbox' && x
                                            ? x.split(',').map((s) => s.trim())
                                            : x,
                                })
                            }
                        />
                    )}

                    <div className="form-check form-switch mb-3">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            id={`req-${field.id}`}
                            checked={field.required}
                            onChange={(e) => patchField({ required: e.target.checked })}
                        />
                        <label className="form-check-label small" htmlFor={`req-${field.id}`}>
                            Required
                        </label>
                    </div>

                    {isChoice(field.type) && (
                        <OptionsManager field={field} patchField={patchField} />
                    )}

                    <ValidationEditor field={field} patchValidation={patchValidation} />

                    <ConditionsEditor
                        field={field}
                        schema={schema}
                        patchField={patchField}
                    />
                </>
            )}
        </div>
    );
}
