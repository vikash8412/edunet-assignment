// Reducer for the builder wizard. The schema object is the single source of
// truth: the canvas, the field-options panel and the raw JSON editor all
// dispatch here and re-render from the same state.

import { allKeys, newField, newSection } from './fieldTypes';

export function schemaReducer(schema, action) {
    switch (action.type) {
        case 'replace':
            return action.schema;

        case 'patch':
            return { ...schema, ...action.patch };

        case 'patchSettings':
            return {
                ...schema,
                settings: { ...schema.settings, ...action.patch },
            };

        case 'addSection':
            return { ...schema, sections: [...schema.sections, newSection(schema.sections)] };

        case 'updateSection':
            return mapSections(schema, (s) =>
                s.id === action.sectionId ? { ...s, ...action.patch } : s,
            );

        case 'removeSection': {
            if (schema.sections.length <= 1) return schema;
            return {
                ...schema,
                sections: schema.sections.filter((s) => s.id !== action.sectionId),
            };
        }

        case 'moveSection': {
            const index = schema.sections.findIndex((s) => s.id === action.sectionId);
            const target = index + action.delta;
            if (index < 0 || target < 0 || target >= schema.sections.length) return schema;
            const sections = [...schema.sections];
            const [moved] = sections.splice(index, 1);
            sections.splice(target, 0, moved);
            return { ...schema, sections };
        }

        case 'addField': {
            const field = newField(action.fieldType, allKeys(schema));
            action.onAdded?.(field);
            return mapSections(schema, (s) => {
                if (s.id !== action.sectionId) return s;
                const fields = [...s.fields];
                fields.splice(action.index ?? fields.length, 0, field);
                return { ...s, fields };
            });
        }

        case 'updateField':
            return mapSections(schema, (s) => ({
                ...s,
                fields: s.fields.map((f) =>
                    f.id === action.fieldId ? { ...f, ...action.patch } : f,
                ),
            }));

        case 'removeField':
            return mapSections(schema, (s) => ({
                ...s,
                fields: s.fields.filter((f) => f.id !== action.fieldId),
            }));

        case 'duplicateField': {
            const keys = allKeys(schema);
            return mapSections(schema, (s) => {
                const index = s.fields.findIndex((f) => f.id === action.fieldId);
                if (index < 0) return s;
                const original = s.fields[index];
                const copy = {
                    ...structuredClone(original),
                    id: newField(original.type, []).id,
                    key: uniqueCopyKey(original.key, keys),
                    label: original.label + ' (copy)',
                };
                action.onAdded?.(copy);
                const fields = [...s.fields];
                fields.splice(index + 1, 0, copy);
                return { ...s, fields };
            });
        }

        case 'moveField': {
            // Move within a section by delta (toolbar up/down arrows).
            return mapSections(schema, (s) => {
                const index = s.fields.findIndex((f) => f.id === action.fieldId);
                if (index < 0) return s;
                const target = index + action.delta;
                if (target < 0 || target >= s.fields.length) return s;
                const fields = [...s.fields];
                const [moved] = fields.splice(index, 1);
                fields.splice(target, 0, moved);
                return { ...s, fields };
            });
        }

        case 'relocateField': {
            // Drag & drop: move a field to (sectionId, index), possibly across sections.
            let moved = null;
            const stripped = mapSections(schema, (s) => {
                const index = s.fields.findIndex((f) => f.id === action.fieldId);
                if (index < 0) return s;
                moved = s.fields[index];
                return { ...s, fields: s.fields.filter((f) => f.id !== action.fieldId) };
            });
            if (!moved) return schema;
            return mapSections(stripped, (s) => {
                if (s.id !== action.sectionId) return s;
                const fields = [...s.fields];
                fields.splice(Math.min(action.index, fields.length), 0, moved);
                return { ...s, fields };
            });
        }

        default:
            return schema;
    }
}

const mapSections = (schema, fn) => ({
    ...schema,
    sections: schema.sections.map(fn),
});

function uniqueCopyKey(key, existingKeys) {
    let candidate = `${key}_copy`;
    let i = 2;
    while (existingKeys.includes(candidate)) candidate = `${key}_copy_${i++}`;
    return candidate;
}
