export default function ApplicationLogo({ className = '' }) {
    return (
        <span className={'app-brand d-inline-flex align-items-center gap-2 ' + className}>
            <i className="bi bi-ui-checks text-primary" />
            Form Builder
        </span>
    );
}
