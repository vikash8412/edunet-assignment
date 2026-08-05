// Client twin of app/Services/Schema/ConditionEvaluator.php — keep in sync.
// The server re-evaluates everything; this only drives live show/hide UX.

import { isInput } from './fieldTypes';

export function computeVisibility(schema, values) {
    const visible = {};

    for (const section of schema.sections) {
        for (const field of section.fields) {
            if (!isInput(field.type)) continue;
            visible[field.key] = passes(field.conditions, values, visible);
        }
    }

    return visible;
}

function passes(conditions, values, visibleSoFar) {
    const rules = conditions?.rules ?? [];
    if (rules.length === 0) return true;

    const logic = conditions.logic ?? 'all';
    const results = rules.map((rule) => {
        const hidden = visibleSoFar[rule.field] === false;
        const actual = hidden ? null : values[rule.field] ?? null;
        return compare(rule.operator, actual, rule.value ?? null);
    });

    return logic === 'any' ? results.some(Boolean) : results.every(Boolean);
}

function compare(operator, actual, expected) {
    switch (operator) {
        case 'equals':
            return looselyEquals(actual, expected);
        case 'not_equals':
            return !looselyEquals(actual, expected);
        case 'contains':
            return contains(actual, expected);
        case 'greater_than':
            return isNumeric(actual) && isNumeric(expected) && Number(actual) > Number(expected);
        case 'less_than':
            return isNumeric(actual) && isNumeric(expected) && Number(actual) < Number(expected);
        case 'is_empty':
            return isEmpty(actual);
        case 'is_not_empty':
            return !isEmpty(actual);
        default:
            return false;
    }
}

const isNumeric = (v) => v !== null && v !== '' && !Array.isArray(v) && !isNaN(Number(v));

function looselyEquals(actual, expected) {
    if (Array.isArray(actual)) {
        return actual.length === 1 && looselyEquals(actual[0], expected);
    }
    if (typeof actual === 'boolean' || typeof expected === 'boolean') {
        return toBool(actual) === toBool(expected);
    }
    return String(actual ?? '') === String(expected ?? '');
}

const toBool = (v) => v === true || v === 'true' || v === '1' || v === 1 || v === 'yes' || v === 'on';

function contains(actual, expected) {
    if (Array.isArray(actual)) return actual.map(String).includes(String(expected));
    if (typeof actual !== 'string' && typeof actual !== 'number') return false;
    return expected !== null && String(actual).toLowerCase().includes(String(expected).toLowerCase());
}

const isEmpty = (v) => v === null || v === undefined || v === '' || (Array.isArray(v) && v.length === 0);
