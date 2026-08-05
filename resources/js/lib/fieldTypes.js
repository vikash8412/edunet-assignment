// UI mirror of app/Services/Schema/FieldTypes.php — the server registry is
// authoritative; this file only drives the palette, canvas and options panel.

let uid = 0;
export const makeId = (prefix) =>
    `${prefix}_${Date.now().toString(36)}${(uid++).toString(36)}${Math.random()
        .toString(36)
        .slice(2, 6)}`;

export const FIELD_TYPES = [
    { type: 'text', label: 'Text input', icon: 'bi-input-cursor-text' },
    { type: 'textarea', label: 'Text area', icon: 'bi-textarea-resize' },
    { type: 'number', label: 'Number input', icon: 'bi-123' },
    { type: 'email', label: 'Email input', icon: 'bi-envelope' },
    { type: 'phone', label: 'Phone input', icon: 'bi-telephone' },
    { type: 'dropdown', label: 'Dropdown', icon: 'bi-menu-button-wide' },
    { type: 'radio', label: 'Radio buttons', icon: 'bi-ui-radios' },
    { type: 'checkbox', label: 'Checkboxes', icon: 'bi-ui-checks' },
    { type: 'date', label: 'Date picker', icon: 'bi-calendar-date' },
    { type: 'rating', label: 'Rating', icon: 'bi-star' },
    { type: 'file', label: 'File upload', icon: 'bi-cloud-arrow-up' },
    { type: 'hidden', label: 'Hidden field', icon: 'bi-eye-slash' },
    { type: 'heading', label: 'Title', icon: 'bi-type-h1' },
    { type: 'paragraph', label: 'Description', icon: 'bi-text-paragraph' },
];

export const CHOICE_TYPES = ['dropdown', 'radio', 'checkbox'];
export const DISPLAY_TYPES = ['heading', 'paragraph'];

export const isChoice = (type) => CHOICE_TYPES.includes(type);
export const isDisplay = (type) => DISPLAY_TYPES.includes(type);
export const isInput = (type) => !isDisplay(type);

export const typeMeta = (type) =>
    FIELD_TYPES.find((t) => t.type === type) ?? {
        type,
        label: type,
        icon: 'bi-question-circle',
    };

const keyFromLabel = (label) =>
    label
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/^(?=[0-9])/, 'f_')
        .slice(0, 60) || 'field';

export function uniqueKey(baseLabel, existingKeys) {
    const base = keyFromLabel(baseLabel);
    let key = base;
    let i = 2;
    while (existingKeys.includes(key)) key = `${base}_${i++}`;
    return key;
}

/** Fresh field of the given type, with a key unique across the schema. */
export function newField(type, existingKeys) {
    const meta = typeMeta(type);
    const field = {
        id: makeId('fld'),
        type,
        key: uniqueKey(meta.label, existingKeys),
        label: meta.label,
        placeholder: null,
        help: null,
        default: null,
        required: false,
        options: [],
        validation: null,
        conditions: null,
    };

    if (isChoice(type)) {
        field.options = [
            { label: 'Option 1', value: 'option_1' },
            { label: 'Option 2', value: 'option_2' },
        ];
    }

    if (type === 'rating') {
        field.validation = { max: 5 };
    }

    if (type === 'heading') {
        field.label = 'Section title';
    }

    if (type === 'paragraph') {
        field.label = 'Descriptive text for your respondents.';
    }

    return field;
}

export function newSection(existing = []) {
    return {
        id: makeId('sec'),
        title: `Section ${existing.length + 1}`,
        description: null,
        fields: [],
    };
}

export function emptySchema() {
    return {
        title: '',
        description: null,
        settings: {
            multi_step: false,
            success_message: null,
            submit_label: null,
            max_per_day: null,
        },
        sections: [
            {
                id: makeId('sec'),
                title: 'Section 1',
                description: null,
                fields: [],
            },
        ],
    };
}

export const allKeys = (schema) =>
    schema.sections.flatMap((s) => s.fields.map((f) => f.key));

export const allInputFields = (schema) =>
    schema.sections.flatMap((s) => s.fields.filter((f) => isInput(f.type)));

export const findField = (schema, fieldId) => {
    for (const section of schema.sections) {
        const field = section.fields.find((f) => f.id === fieldId);
        if (field) return { field, section };
    }
    return { field: null, section: null };
};
