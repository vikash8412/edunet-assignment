// Brand mark: a four-point spark (generation) sitting over a folded-corner
// form shape, so the glyph reads as "AI" and "form" at once rather than a
// generic checklist icon.
function BrandGlyph() {
    return (
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path
                d="M6 3.5h8L18 7.5V19a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 19V3.5Z"
                fill="rgba(255,255,255,0.16)"
            />
            <path d="M13.5 3.5 18 7.5h-3.2a1.3 1.3 0 0 1-1.3-1.3V3.5Z" fill="rgba(255,255,255,0.3)" />
            <path
                d="M18.2 12.4c.55 1.55 1.05 2.05 2.6 2.6-1.55.55-2.05 1.05-2.6 2.6-.55-1.55-1.05-2.05-2.6-2.6 1.55-.55 2.05-1.05 2.6-2.6Z"
                fill="#fff"
            />
        </svg>
    );
}

export default function ApplicationLogo({ className = '', subtitle = true }) {
    return (
        <span className={'app-brand ' + className}>
            <span className="app-brand-mark">
                <BrandGlyph />
            </span>
            <span>
                <span className="app-brand-word">
                    AI Form <em>Builder</em>
                </span>
                {subtitle && <span className="app-brand-sub">Generate. Build. Collect.</span>}
            </span>
        </span>
    );
}
