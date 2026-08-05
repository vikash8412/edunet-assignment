import { useDraggable } from '@dnd-kit/core';
import { FIELD_TYPES } from '@/lib/fieldTypes';

function PaletteTile({ meta, onAdd }) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id: `palette:${meta.type}`,
        data: { fromPalette: true, fieldType: meta.type },
    });

    return (
        <div
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            className="palette-tile"
            style={{ opacity: isDragging ? 0.4 : 1, touchAction: 'none' }}
            onClick={onAdd}
            title={`Add ${meta.label} (click or drag to canvas)`}
        >
            <i className={`bi ${meta.icon}`} />
            {meta.label}
        </div>
    );
}

export default function PaletteTab({ onAdd }) {
    return (
        <div>
            <div className="palette-title mb-2">Standard fields</div>
            <div className="palette-grid">
                {FIELD_TYPES.map((meta) => (
                    <PaletteTile
                        key={meta.type}
                        meta={meta}
                        onAdd={() => onAdd(meta.type)}
                    />
                ))}
            </div>
            <div className="form-text mt-3 small">
                Click a tile to append it, or drag it onto the canvas.
            </div>
        </div>
    );
}
