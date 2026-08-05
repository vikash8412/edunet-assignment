import { useEffect, useState } from 'react';

/**
 * Raw JSON editor, two-way synced with the canvas: canvas edits re-render the
 * text; applying valid JSON here replaces the schema store. Invalid JSON never
 * touches the canvas — it only shows an inline error.
 */
export default function JsonEditorPanel({ schema, onApply, serverErrors }) {
    const [text, setText] = useState(() => JSON.stringify(schema, null, 2));
    const [dirty, setDirty] = useState(false);
    const [parseError, setParseError] = useState(null);

    // Canvas changed elsewhere: refresh the editor unless the user is mid-edit.
    useEffect(() => {
        if (!dirty) {
            setText(JSON.stringify(schema, null, 2));
            setParseError(null);
        }
    }, [schema, dirty]);

    const apply = () => {
        try {
            const parsed = JSON.parse(text);
            setParseError(null);
            setDirty(false);
            onApply(parsed);
        } catch (e) {
            setParseError(e.message);
        }
    };

    const discard = () => {
        setText(JSON.stringify(schema, null, 2));
        setDirty(false);
        setParseError(null);
    };

    return (
        <div className="mt-3">
            <div className="d-flex align-items-center justify-content-between mb-2">
                <strong className="small">
                    <i className="bi bi-code-slash me-1" />
                    Schema JSON — single source of truth
                </strong>
                <div className="d-flex gap-2">
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary"
                        disabled={!dirty}
                        onClick={discard}
                    >
                        Discard edits
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-primary"
                        disabled={!dirty}
                        onClick={apply}
                    >
                        Apply to canvas
                    </button>
                </div>
            </div>

            <textarea
                className={'form-control json-editor ' + (parseError ? 'is-invalid' : '')}
                spellCheck={false}
                value={text}
                onChange={(e) => {
                    setText(e.target.value);
                    setDirty(true);
                }}
            />

            {parseError && (
                <div className="text-danger small mt-1">
                    <i className="bi bi-exclamation-triangle me-1" />
                    Invalid JSON: {parseError}
                </div>
            )}

            {serverErrors && serverErrors.length > 0 && (
                <div className="alert alert-danger small mt-2 mb-0">
                    <strong>The server rejected this schema:</strong>
                    <ul className="mb-0 mt-1">
                        {serverErrors.map((error, i) => (
                            <li key={i}>{error}</li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
