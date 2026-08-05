import { SortableContext, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useDroppable } from '@dnd-kit/core';
import FieldCard from './FieldCard';
import { makeId } from '@/lib/fieldTypes';

function SectionBlock({
    section,
    sectionCount,
    selectedFieldId,
    dispatch,
    onSelectField,
    onEditField,
}) {
    const { setNodeRef, isOver } = useDroppable({ id: `section:${section.id}` });

    return (
        <div className="section-card p-3">
            <div className="d-flex align-items-start gap-2 mb-2">
                <div className="flex-grow-1 min-w-0">
                    <input
                        className="form-control form-control-sm fw-bold border-0 px-0 fs-6"
                        value={section.title}
                        placeholder="Section title"
                        onChange={(e) =>
                            dispatch({
                                type: 'updateSection',
                                sectionId: section.id,
                                patch: { title: e.target.value },
                            })
                        }
                    />
                    <input
                        className="form-control form-control-sm text-secondary border-0 px-0"
                        value={section.description ?? ''}
                        placeholder="Section description (optional)"
                        onChange={(e) =>
                            dispatch({
                                type: 'updateSection',
                                sectionId: section.id,
                                patch: { description: e.target.value || null },
                            })
                        }
                    />
                </div>
                <div className="d-flex gap-1">
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary border-0"
                        title="Move section up"
                        onClick={() =>
                            dispatch({ type: 'moveSection', sectionId: section.id, delta: -1 })
                        }
                    >
                        <i className="bi bi-chevron-up" />
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary border-0"
                        title="Move section down"
                        onClick={() =>
                            dispatch({ type: 'moveSection', sectionId: section.id, delta: 1 })
                        }
                    >
                        <i className="bi bi-chevron-down" />
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-danger border-0"
                        title={
                            sectionCount <= 1
                                ? 'A form needs at least one section'
                                : 'Delete section and its fields'
                        }
                        disabled={sectionCount <= 1}
                        onClick={() =>
                            dispatch({ type: 'removeSection', sectionId: section.id })
                        }
                    >
                        <i className="bi bi-trash" />
                    </button>
                </div>
            </div>

            <SortableContext
                items={section.fields.map((f) => f.id)}
                strategy={verticalListSortingStrategy}
            >
                <div
                    ref={setNodeRef}
                    className={'builder-canvas ' + (isOver ? 'drag-over' : '')}
                    style={{ minHeight: section.fields.length ? 'auto' : 140 }}
                >
                    {section.fields.length === 0 && (
                        <div className="canvas-empty py-4">
                            <i className="bi bi-box-arrow-in-down fs-3 d-block mb-2" />
                            Drag a field here, or click one in the palette
                        </div>
                    )}

                    {section.fields.map((field) => (
                        <FieldCard
                            key={field.id}
                            field={field}
                            selected={field.id === selectedFieldId}
                            hasConditions={(field.conditions?.rules?.length ?? 0) > 0}
                            onSelect={() => onSelectField(field.id)}
                            onEdit={() => onEditField(field.id)}
                            onMove={(delta) =>
                                dispatch({ type: 'moveField', fieldId: field.id, delta })
                            }
                            onDuplicate={() => {
                                const copyId = makeId('fld');
                                dispatch({
                                    type: 'duplicateField',
                                    fieldId: field.id,
                                    copyId,
                                });
                                onSelectField(copyId);
                            }}
                            onRemove={() =>
                                dispatch({ type: 'removeField', fieldId: field.id })
                            }
                        />
                    ))}
                </div>
            </SortableContext>
        </div>
    );
}

export default function Canvas({
    schema,
    selectedFieldId,
    dispatch,
    onSelectField,
    onEditField,
}) {
    return (
        <div>
            {schema.sections.map((section) => (
                <SectionBlock
                    key={section.id}
                    section={section}
                    sectionCount={schema.sections.length}
                    selectedFieldId={selectedFieldId}
                    dispatch={dispatch}
                    onSelectField={onSelectField}
                    onEditField={onEditField}
                />
            ))}

            <button
                type="button"
                className="btn btn-outline-primary btn-sm"
                onClick={() => dispatch({ type: 'addSection' })}
            >
                <i className="bi bi-plus-lg me-1" />
                Add section
            </button>
        </div>
    );
}
