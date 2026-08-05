import { useState } from 'react';

/** Interactive input for the public fill page, one per field type. */
export default function FieldInput({ field, value, error, onChange }) {
    switch (field.type) {
        case 'heading':
            return <h5 className="mt-2 mb-0">{field.label}</h5>;
        case 'paragraph':
            return <p className="text-secondary mb-0">{field.label}</p>;
        case 'hidden':
            return (
                <input type="hidden" name={field.key} value={value ?? field.default ?? ''} />
            );
        default:
            return (
                <div>
                    <label className="form-label fw-semibold mb-1" htmlFor={field.id}>
                        {field.label}
                        {field.required && <span className="text-danger ms-1">*</span>}
                    </label>
                    <Control field={field} value={value} error={error} onChange={onChange} />
                    {field.help && <div className="form-text">{field.help}</div>}
                    {error && <div className="text-danger small mt-1">{error}</div>}
                </div>
            );
    }
}

function Control({ field, value, error, onChange }) {
    const invalid = error ? ' is-invalid' : '';

    switch (field.type) {
        case 'textarea':
            return (
                <textarea
                    id={field.id}
                    className={'form-control' + invalid}
                    rows={4}
                    placeholder={field.placeholder ?? ''}
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                />
            );

        case 'number':
            return (
                <input
                    id={field.id}
                    type="number"
                    className={'form-control' + invalid}
                    placeholder={field.placeholder ?? ''}
                    value={value ?? ''}
                    min={field.validation?.min ?? undefined}
                    max={field.validation?.max ?? undefined}
                    step={field.validation?.integer ? 1 : 'any'}
                    onChange={(e) => onChange(e.target.value)}
                />
            );

        case 'date':
            return (
                <input
                    id={field.id}
                    type="date"
                    className={'form-control' + invalid}
                    value={value ?? ''}
                    min={field.validation?.minDate ?? undefined}
                    max={field.validation?.maxDate ?? undefined}
                    onChange={(e) => onChange(e.target.value)}
                />
            );

        case 'dropdown':
            return (
                <select
                    id={field.id}
                    className={'form-select' + invalid}
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                >
                    <option value="">{field.placeholder ?? 'Select…'}</option>
                    {field.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            );

        case 'radio':
            return (
                <div className={error ? 'is-invalid' : ''}>
                    {field.options.map((option) => (
                        <div className="form-check" key={option.value}>
                            <input
                                className="form-check-input"
                                type="radio"
                                name={field.key}
                                id={`${field.id}-${option.value}`}
                                checked={String(value ?? '') === String(option.value)}
                                onChange={() => onChange(option.value)}
                            />
                            <label
                                className="form-check-label"
                                htmlFor={`${field.id}-${option.value}`}
                            >
                                {option.label}
                            </label>
                        </div>
                    ))}
                </div>
            );

        case 'checkbox': {
            const selected = Array.isArray(value) ? value : [];
            const toggle = (optionValue) =>
                onChange(
                    selected.includes(optionValue)
                        ? selected.filter((v) => v !== optionValue)
                        : [...selected, optionValue],
                );

            return (
                <div className={error ? 'is-invalid' : ''}>
                    {field.options.map((option) => (
                        <div className="form-check" key={option.value}>
                            <input
                                className="form-check-input"
                                type="checkbox"
                                id={`${field.id}-${option.value}`}
                                checked={selected.includes(option.value)}
                                onChange={() => toggle(option.value)}
                            />
                            <label
                                className="form-check-label"
                                htmlFor={`${field.id}-${option.value}`}
                            >
                                {option.label}
                            </label>
                        </div>
                    ))}
                </div>
            );
        }

        case 'rating':
            return <RatingStars field={field} value={value} onChange={onChange} />;

        case 'file':
            return (
                <input
                    id={field.id}
                    type="file"
                    className={'form-control' + invalid}
                    accept={(field.validation?.mimes ?? []).map((m) => '.' + m).join(',') || undefined}
                    onChange={(e) => onChange(e.target.files[0] ?? null)}
                />
            );

        default:
            return (
                <input
                    id={field.id}
                    type={field.type === 'email' ? 'email' : field.type === 'phone' ? 'tel' : 'text'}
                    className={'form-control' + invalid}
                    placeholder={field.placeholder ?? ''}
                    value={value ?? ''}
                    maxLength={field.validation?.maxLength ?? undefined}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
    }
}

function RatingStars({ field, value, onChange }) {
    const [hover, setHover] = useState(0);
    const max = field.validation?.max ?? 5;
    const current = Number(value) || 0;

    return (
        <div onMouseLeave={() => setHover(0)}>
            {Array.from({ length: max }, (_, i) => {
                const star = i + 1;
                const active = star <= (hover || current);
                return (
                    <i
                        key={star}
                        role="button"
                        aria-label={`${star} of ${max}`}
                        className={
                            'rating-star bi me-1 ' +
                            (active ? 'bi-star-fill active' : 'bi-star')
                        }
                        onMouseEnter={() => setHover(star)}
                        onClick={() => onChange(star)}
                    />
                );
            })}
            {current > 0 && (
                <span className="small text-secondary ms-2">
                    {current}/{max}
                </span>
            )}
        </div>
    );
}
