import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import FieldPreview from './FieldPreview';
import { isDisplay, typeMeta } from '@/lib/fieldTypes';

export default function FieldCard({
    field,
    selected,
    hasConditions,
    onSelect,
    onEdit,
    onMove,
    onDuplicate,
    onRemove,
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
        useSortable({ id: field.id });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    const meta = typeMeta(field.type);

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={[
                'field-card',
                selected && 'selected',
                isDragging && 'dragging',
            ]
                .filter(Boolean)
                .join(' ')}
            onClick={onSelect}
        >
            <div className="field-toolbar" onClick={(e) => e.stopPropagation()}>
                <button
                    type="button"
                    className="btn drag-handle"
                    title="Drag to reorder"
                    {...attributes}
                    {...listeners}
                >
                    <i className="bi bi-arrows-move" />
                </button>
                <button type="button" className="btn" title="Move up" onClick={() => onMove(-1)}>
                    <i className="bi bi-chevron-up" />
                </button>
                <button type="button" className="btn" title="Move down" onClick={() => onMove(1)}>
                    <i className="bi bi-chevron-down" />
                </button>
                <button type="button" className="btn" title="Duplicate" onClick={onDuplicate}>
                    <i className="bi bi-copy" />
                </button>
                <button type="button" className="btn" title="Edit field" onClick={onEdit}>
                    <i className="bi bi-pencil-square" />
                </button>
                <button
                    type="button"
                    className="btn text-danger-hover"
                    title="Delete"
                    onClick={onRemove}
                >
                    <i className="bi bi-trash" />
                </button>
            </div>

            {!isDisplay(field.type) && (
                <div className="field-label mb-1">
                    <i className={`bi ${meta.icon} me-1 text-secondary`} />
                    {field.label}
                    {field.required && <span className="text-danger ms-1">*</span>}
                    {hasConditions && (
                        <span
                            className="badge text-bg-light border ms-2"
                            title="Shown conditionally"
                        >
                            <i className="bi bi-signpost-split me-1" />
                            conditional
                        </span>
                    )}
                </div>
            )}

            <div className="field-preview">
                <FieldPreview field={field} />
            </div>

            {field.help && <div className="form-text small">{field.help}</div>}
        </div>
    );
}
