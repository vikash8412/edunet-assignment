export default function InputLabel({
    value,
    required = false,
    className = '',
    children,
    ...props
}) {
    return (
        <label {...props} className={'form-label fw-semibold ' + className}>
            {value ? value : children}
            {required && <span className="text-danger ms-1">*</span>}
        </label>
    );
}
