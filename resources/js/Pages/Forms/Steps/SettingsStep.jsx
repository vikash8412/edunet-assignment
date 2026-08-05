export default function SettingsStep({
    schema,
    dispatch,
    status,
    setStatus,
    saving,
    onBack,
    onSave,
    onCancel,
}) {
    const settings = schema.settings ?? {};
    const patch = (p) => dispatch({ type: 'patchSettings', patch: p });

    return (
        <div className="card">
            <div className="card-body p-4">
                <h5 className="mb-1">Settings</h5>
                <p className="text-secondary small mb-4">
                    Control how the form is presented and protected.
                </p>

                <div style={{ maxWidth: 560 }}>
                    <label className="form-label fw-semibold">Status</label>
                    <select
                        className="form-select"
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                    >
                        <option value="draft">Draft — only you can see it</option>
                        <option value="published">Published — public link accepts responses</option>
                        <option value="archived">Archived — link shows a closed notice</option>
                    </select>

                    <div className="form-check form-switch mt-4">
                        <input
                            className="form-check-input"
                            type="checkbox"
                            id="multi-step"
                            checked={!!settings.multi_step}
                            onChange={(e) => patch({ multi_step: e.target.checked })}
                        />
                        <label className="form-check-label" htmlFor="multi-step">
                            Multi-step form
                            <div className="form-text mt-0">
                                Each section becomes its own step with progress shown.
                            </div>
                        </label>
                    </div>

                    <label className="form-label fw-semibold mt-4">Success message</label>
                    <textarea
                        className="form-control"
                        rows={2}
                        placeholder="Thanks! Your response has been recorded."
                        value={settings.success_message ?? ''}
                        onChange={(e) => patch({ success_message: e.target.value || null })}
                    />

                    <div className="row g-3 mt-1">
                        <div className="col-sm-6">
                            <label className="form-label fw-semibold">Submit button label</label>
                            <input
                                className="form-control"
                                placeholder="Submit"
                                maxLength={60}
                                value={settings.submit_label ?? ''}
                                onChange={(e) => patch({ submit_label: e.target.value || null })}
                            />
                        </div>
                        <div className="col-sm-6">
                            <label className="form-label fw-semibold">
                                Daily submission cap
                            </label>
                            <input
                                type="number"
                                min={1}
                                className="form-control"
                                placeholder="Unlimited"
                                value={settings.max_per_day ?? ''}
                                onChange={(e) =>
                                    patch({
                                        max_per_day: e.target.value
                                            ? Number(e.target.value)
                                            : null,
                                    })
                                }
                            />
                            <div className="form-text">
                                Spam guard: total responses accepted per day.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="card-footer bg-white d-flex justify-content-between py-3">
                <button type="button" className="btn btn-outline-danger" onClick={onCancel}>
                    Cancel
                </button>
                <div className="d-flex gap-2">
                    <button type="button" className="btn btn-outline-secondary" onClick={onBack}>
                        <i className="bi bi-arrow-left me-1" /> Back
                    </button>
                    <button
                        type="button"
                        className="btn btn-primary"
                        disabled={saving}
                        onClick={onSave}
                    >
                        {saving ? (
                            <>
                                <span className="spinner-border spinner-border-sm me-2" />
                                Saving…
                            </>
                        ) : (
                            <>
                                Save &amp; finish <i className="bi bi-check-lg ms-1" />
                            </>
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
