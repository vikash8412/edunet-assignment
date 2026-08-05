import { useState } from 'react';
import { Link } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';

export default function FinishStep({ form, status }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(form.public_url);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard unavailable (non-HTTPS); the input below is selectable.
        }
    };

    return (
        <div className="card">
            <div className="card-body p-4 text-center">
                <div className="text-success fs-1 mb-2">
                    <i className="bi bi-check-circle" />
                </div>
                <h5>Your form is saved</h5>
                <p className="text-secondary small">
                    {status === 'published'
                        ? 'Share the public link below — it accepts responses right away.'
                        : 'The form is currently a draft. Publish it in Settings to accept responses.'}
                </p>

                <div className="d-flex justify-content-center my-4">
                    <div className="border rounded p-3 bg-white">
                        <QRCodeSVG value={form.public_url} size={148} />
                    </div>
                </div>

                <div
                    className="input-group mx-auto mb-4"
                    style={{ maxWidth: 480 }}
                >
                    <input
                        className="form-control font-monospace small"
                        readOnly
                        value={form.public_url}
                        onFocus={(e) => e.target.select()}
                    />
                    <button type="button" className="btn btn-outline-primary" onClick={copy}>
                        {copied ? (
                            <>
                                <i className="bi bi-check-lg me-1" />
                                Copied
                            </>
                        ) : (
                            <>
                                <i className="bi bi-clipboard me-1" />
                                Copy
                            </>
                        )}
                    </button>
                </div>

                <div className="d-flex justify-content-center gap-2 flex-wrap">
                    <a
                        href={form.public_url}
                        target="_blank"
                        rel="noreferrer"
                        className="btn btn-primary"
                    >
                        <i className="bi bi-box-arrow-up-right me-1" />
                        Open public form
                    </a>
                    <a
                        href={`/forms/${form.id}/submissions`}
                        className="btn btn-outline-primary"
                    >
                        <i className="bi bi-inbox me-1" />
                        View submissions
                    </a>
                    <Link href={route('forms.index')} className="btn btn-outline-secondary">
                        Back to my forms
                    </Link>
                </div>
            </div>
        </div>
    );
}
