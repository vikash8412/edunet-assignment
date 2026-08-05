import { useState } from 'react';
import {
    DndContext,
    PointerSensor,
    pointerWithin,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import Canvas from '@/Components/Builder/Canvas';
import PaletteTab from '@/Components/Builder/PaletteTab';
import FieldOptionsTab from '@/Components/Builder/FieldOptionsTab';
import JsonEditorPanel from '@/Components/Builder/JsonEditorPanel';
import { findField } from '@/lib/fieldTypes';

export default function BuilderStep({
    schema,
    dispatch,
    serverErrors,
    aiPanel,
    onBack,
    onNext,
    onCancel,
}) {
    const [selectedFieldId, setSelectedFieldId] = useState(null);
    const [tab, setTab] = useState('add');
    const [showJson, setShowJson] = useState(false);

    const { field: selectedField } = findField(schema, selectedFieldId);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    );

    const selectField = (fieldId) => {
        setSelectedFieldId(fieldId);
    };

    const editField = (fieldId) => {
        setSelectedFieldId(fieldId);
        setTab('options');
    };

    const appendField = (fieldType) => {
        const lastSection = schema.sections[schema.sections.length - 1];
        dispatch({
            type: 'addField',
            sectionId: lastSection.id,
            fieldType,
            onAdded: (f) => editField(f.id),
        });
    };

    const resolveTarget = (overId) => {
        if (String(overId).startsWith('section:')) {
            const sectionId = String(overId).slice('section:'.length);
            const section = schema.sections.find((s) => s.id === sectionId);
            return { sectionId, index: section ? section.fields.length : 0 };
        }
        for (const section of schema.sections) {
            const index = section.fields.findIndex((f) => f.id === overId);
            if (index >= 0) return { sectionId: section.id, index };
        }
        return null;
    };

    const handleDragEnd = ({ active, over }) => {
        if (!over) return;
        const target = resolveTarget(over.id);
        if (!target) return;

        if (active.data.current?.fromPalette) {
            dispatch({
                type: 'addField',
                sectionId: target.sectionId,
                index: target.index,
                fieldType: active.data.current.fieldType,
                onAdded: (f) => editField(f.id),
            });
            return;
        }

        if (active.id !== over.id) {
            dispatch({
                type: 'relocateField',
                fieldId: active.id,
                sectionId: target.sectionId,
                index: target.index,
            });
        }
    };

    return (
        <div className="card">
            <div className="card-body p-4">
                <DndContext
                    sensors={sensors}
                    collisionDetection={pointerWithin}
                    onDragEnd={handleDragEnd}
                >
                    <div className="row g-4">
                        <div className="col-lg-8">
                            {aiPanel}
                            <Canvas
                                schema={schema}
                                selectedFieldId={selectedFieldId}
                                dispatch={dispatch}
                                onSelectField={selectField}
                                onEditField={editField}
                            />
                        </div>

                        <div className="col-lg-4">
                            <ul className="nav nav-tabs nav-fill mb-3">
                                <li className="nav-item">
                                    <button
                                        type="button"
                                        className={'nav-link ' + (tab === 'add' ? 'active' : '')}
                                        onClick={() => setTab('add')}
                                    >
                                        Add fields
                                    </button>
                                </li>
                                <li className="nav-item">
                                    <button
                                        type="button"
                                        className={
                                            'nav-link ' + (tab === 'options' ? 'active' : '')
                                        }
                                        onClick={() => setTab('options')}
                                    >
                                        Field options
                                    </button>
                                </li>
                            </ul>

                            {tab === 'add' ? (
                                <PaletteTab onAdd={appendField} />
                            ) : (
                                <FieldOptionsTab
                                    schema={schema}
                                    field={selectedField}
                                    dispatch={dispatch}
                                />
                            )}
                        </div>
                    </div>
                </DndContext>

                <div className="mt-3 border-top pt-3">
                    <button
                        type="button"
                        className="btn btn-sm btn-outline-secondary"
                        onClick={() => setShowJson(!showJson)}
                    >
                        <i className="bi bi-code-slash me-1" />
                        {showJson ? 'Hide schema JSON' : 'Edit schema JSON'}
                    </button>

                    {showJson && (
                        <JsonEditorPanel
                            schema={schema}
                            serverErrors={serverErrors}
                            onApply={(parsed) => dispatch({ type: 'replace', schema: parsed })}
                        />
                    )}

                    {!showJson && serverErrors?.length > 0 && (
                        <div className="alert alert-danger small mt-3 mb-0">
                            <strong>The server rejected this schema:</strong>
                            <ul className="mb-0 mt-1">
                                {serverErrors.map((error, i) => (
                                    <li key={i}>{error}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            <div className="card-footer bg-white d-flex justify-content-between py-3">
                <button type="button" className="btn btn-outline-danger" onClick={onCancel}>
                    Cancel
                </button>
                <div className="d-flex gap-2">
                    <button type="button" className="btn btn-outline-secondary" onClick={onBack}>
                        <i className="bi bi-arrow-left me-1" /> Back
                    </button>
                    <button type="button" className="btn btn-primary" onClick={onNext}>
                        Next: Settings <i className="bi bi-arrow-right ms-1" />
                    </button>
                </div>
            </div>
        </div>
    );
}
