import { Head } from '@inertiajs/react';

export default function Closed({ title }) {
    return (
        <div className="py-5 px-3">
            <Head title={title} />
            <div className="fill-shell mx-auto">
                <div className="card">
                    <div className="card-body text-center py-5">
                        <div className="text-secondary fs-1 mb-2">
                            <i className="bi bi-lock" />
                        </div>
                        <h4>{title}</h4>
                        <p className="text-secondary mb-0">
                            This form is no longer accepting responses.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
