export default function DetailsStep({ schema, dispatch, publicUrlPreview, onNext, onCancel }) {
    const titleLength = schema.title.length;

    return (
        <div className="card">
            <div className="card-body p-4">
                <div className="d-flex justify-content-between align-items-start mb-1">
                    <div>
                        <h5 className="mb-1">Form basics</h5>
                        <p className="text-secondary small mb-0">
                            Enter the primary details for your new data-collection form.
                        </p>
                    </div>
                    <span className="badge text-bg-primary">Survey form</span>
                </div>

                <div className="mt-4" style={{ maxWidth: 560 }}>
                    <label className="form-label fw-semibold">
                        Form title <span className="text-danger">*</span>
                    </label>
                    <input
                        className="form-control"
                        placeholder="e.g., Fall 2026 Registration"
                        maxLength={200}
                        value={schema.title}
                        onChange={(e) => dispatch({ type: 'patch', patch: { title: e.target.value } })}
                        autoFocus
                    />
                    <div className="char-counter mt-1">{titleLength}/200</div>

                    <label className="form-label fw-semibold mt-3">Description</label>
                    <textarea
                        className="form-control"
                        rows={3}
                        placeholder="Optional intro shown above the form"
                        value={schema.description ?? ''}
                        onChange={(e) =>
                            dispatch({
                                type: 'patch',
                                patch: { description: e.target.value || null },
                            })
                        }
                    />

                    <div className="public-url-hint mt-3">
                        Public URL: {publicUrlPreview}
                    </div>
                </div>
            </div>

            <div className="card-footer bg-white d-flex justify-content-between py-3">
                <button type="button" className="btn btn-outline-danger" onClick={onCancel}>
                    Cancel
                </button>
                <button
                    type="button"
                    className="btn btn-primary"
                    disabled={titleLength === 0}
                    onClick={onNext}
                >
                    Next: Builder <i className="bi bi-arrow-right ms-1" />
                </button>
            </div>
        </div>
    );
}
