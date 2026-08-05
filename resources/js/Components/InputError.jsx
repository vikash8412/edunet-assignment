export default function InputError({ message, className = '', ...props }) {
    return message ? (
        <div {...props} className={'text-danger small mt-1 ' + className}>
            {message}
        </div>
    ) : null;
}
