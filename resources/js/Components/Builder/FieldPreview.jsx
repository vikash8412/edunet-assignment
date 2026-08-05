// Non-interactive rendering of a field as it will appear on the fill page.
// Used inside canvas cards; inputs are pointer-events:none via .field-preview.

export default function FieldPreview({ field }) {
    switch (field.type) {
        case 'heading':
            return <h5 className="mb-0">{field.label}</h5>;

        case 'paragraph':
            return <p className="text-secondary small mb-0">{field.label}</p>;

        case 'textarea':
            return (
                <textarea
                    className="form-control form-control-sm"
                    rows={2}
                    placeholder={field.placeholder ?? ''}
                    readOnly
                />
            );

        case 'dropdown':
            return (
                <select className="form-select form-select-sm" disabled>
                    <option>{field.placeholder ?? 'Select…'}</option>
                    {field.options.map((o) => (
                        <option key={o.value}>{o.label}</option>
                    ))}
                </select>
            );

        case 'radio':
        case 'checkbox':
            return (
                <div>
                    {field.options.slice(0, 4).map((o) => (
                        <div className="form-check" key={o.value}>
                            <input
                                className="form-check-input"
                                type={field.type === 'radio' ? 'radio' : 'checkbox'}
                                readOnly
                                disabled
                            />
                            <label className="form-check-label small">{o.label}</label>
                        </div>
                    ))}
                    {field.options.length > 4 && (
                        <span className="small text-secondary">
                            +{field.options.length - 4} more…
                        </span>
                    )}
                </div>
            );

        case 'rating': {
            const max = field.validation?.max ?? 5;
            return (
                <div>
                    {Array.from({ length: max }, (_, i) => (
                        <i key={i} className="bi bi-star rating-star" />
                    ))}
                </div>
            );
        }

        case 'file':
            return (
                <input type="file" className="form-control form-control-sm" disabled />
            );

        case 'hidden':
            return (
                <div className="small text-secondary fst-italic">
                    <i className="bi bi-eye-slash me-1" />
                    Hidden field — not shown to respondents
                </div>
            );

        default:
            return (
                <input
                    type={field.type === 'date' ? 'date' : 'text'}
                    className="form-control form-control-sm"
                    placeholder={field.placeholder ?? ''}
                    readOnly
                />
            );
    }
}
