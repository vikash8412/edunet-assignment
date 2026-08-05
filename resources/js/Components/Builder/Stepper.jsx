const STEPS = [
    { id: 'details', label: 'Details' },
    { id: 'builder', label: 'Builder' },
    { id: 'settings', label: 'Settings' },
    { id: 'finish', label: 'Finish' },
];

export default function Stepper({ current, maxReached, onNavigate }) {
    const currentIndex = STEPS.findIndex((s) => s.id === current);
    const maxIndex = STEPS.findIndex((s) => s.id === maxReached);

    return (
        <div className="stepper mb-4" role="navigation" aria-label="Form wizard steps">
            {STEPS.map((step, i) => {
                const done = i < currentIndex;
                const active = i === currentIndex;
                const reachable = i <= maxIndex && !active && onNavigate;

                return (
                    <div key={step.id} className="d-contents">
                        {i > 0 && <div className="step-line" />}
                        <div
                            className={[
                                'step',
                                active && 'active',
                                done && 'done',
                                reachable && 'clickable',
                            ]
                                .filter(Boolean)
                                .join(' ')}
                            onClick={reachable ? () => onNavigate(step.id) : undefined}
                        >
                            <span className="step-circle">
                                {done ? <i className="bi bi-check-lg" /> : i + 1}
                            </span>
                            <span className="step-label">{step.label}</span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
