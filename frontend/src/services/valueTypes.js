// Shared helpers for ontology value types, including enum value types.
//
// Enums are modeled the same way Maker/Foundry model them: a string value type
// carrying a "oneOf" constraint:
//   { baseType: { type: 'string' }, constraints: [{ type: 'oneOf', values: [...], useIgnoreCase: false }] }
// Inline ontology row enums (data.sqlType = "ENUM('a','b')") are promoted into
// reusable value types with a oneOf constraint so they show up in the Value Types modal.

const ENUM_RE = /^(?:ENUM|SET)\s*\((.*)\)$/i

export function isEnumSqlType(sqlType) {
    return typeof sqlType === 'string' && ENUM_RE.test(sqlType.trim())
}

export function parseEnumValues(sqlType) {
    if (typeof sqlType !== 'string') return []
    const match = sqlType.trim().match(ENUM_RE)
    if (!match || !match[1].trim()) return []
    const values = []
    const re = /'((?:\\.|[^'])*)'|"((?:\\.|[^"])*)"/g
    let token
    while ((token = re.exec(match[1])) !== null) {
        const raw = token[1] ?? token[2] ?? ''
        const value = raw.replace(/\\(['"\\])/g, '$1').trim()
        if (value !== '') values.push(value)
    }
    return values
}

export function buildEnumSqlType(values) {
    if (!values.length) return "ENUM('')"
    return `ENUM(${values.map(value => `'${String(value).replace(/'/g, "\\'")}'`).join(',')})`
}

export function oneOfConstraintOf(valueType) {
    return (valueType?.constraints ?? []).find(constraint => constraint?.type === 'oneOf') ?? null
}

// Display type shown in the row type dropdown / compact row for a referenced value type.
export function canvasTypeForValueType(valueType) {
    const baseType = valueType?.baseType ?? { type: 'string' }
    if (baseType.type === 'array') return `ARRAY<${String(baseType.elementType ?? 'string').toUpperCase()}>`
    if (baseType.type === 'struct') return 'STRUCT'
    if (baseType.type === 'decimal') return 'DECIMAL(10,2)'
    return String(baseType.type ?? 'string').toUpperCase()
}

// Reduce a canvas/sql type string to a comparable base-type token, e.g.
// 'DECIMAL(10,2)' -> 'DECIMAL', 'ARRAY<STRING>' -> 'ARRAY', 'VARCHAR(255)' -> 'VARCHAR'.
export function baseTypeToken(type) {
    if (!type) return ''
    return String(type)
        .toUpperCase()
        .trim()
        .replace(/<.*>$/, '')
        .replace(/\(.*\)$/, '')
        .trim()
}

// The comparable base type of a row: its value type's base type when one is
// referenced, otherwise its plain sql/canvas type. `valueTypeById` is a Map.
export function effectiveBaseTypeToken(rowData, valueTypeById) {
    const valueTypeId = rowData?.valueTypeId
    if (valueTypeId && valueTypeById?.get) {
        const valueType = valueTypeById.get(valueTypeId)
        if (valueType) return baseTypeToken(canvasTypeForValueType(valueType))
    }
    return baseTypeToken(rowData?.sqlType)
}

export function valueTypeBaseLabel(valueType) {
    const label = (type) => String(type ?? 'string').charAt(0).toUpperCase() + String(type ?? 'string').slice(1)
    const oneOf = oneOfConstraintOf(valueType)
    if (oneOf) {
        const count = Array.isArray(oneOf.values) ? oneOf.values.length : 0
        const suffix = oneOf.useIgnoreCase ? ' · ignore case' : ''
        return `Enum · ${count} value${count === 1 ? '' : 's'}${suffix}`
    }
    const baseType = valueType?.baseType ?? { type: 'string' }
    if (baseType.type === 'array') return `Array<${label(baseType.elementType ?? 'string')}>`
    return label(baseType.type)
}

const splitWords = (parts) => parts
    .filter(Boolean)
    .join(' ')
    .split(/[^A-Za-z0-9]+/)
    .filter(Boolean)

export function valueTypeApiNameFrom(...parts) {
    const words = splitWords(parts)
    if (!words.length) return 'valueType'
    const head = words[0].toLowerCase()
    const tail = words.slice(1).map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join('')
    let name = head + tail
    if (!/^[A-Za-z]/.test(name)) name = `vt${name.charAt(0).toUpperCase()}${name.slice(1)}`
    return name.slice(0, 100)
}

export function valueTypeDisplayNameFrom(...parts) {
    const words = splitWords(parts)
    if (!words.length) return 'Value Type'
    return words.map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ')
}

// Promote inline ontology row enums into reusable value types with a oneOf constraint.
// Returns a new schema/valueTypes pair and whether anything changed.
// Each materialized row becomes its own value type (no merging by values).
export function materializeInlineEnumValueTypes(schema, valueTypes) {
    if (!Array.isArray(schema) || !Array.isArray(valueTypes)) {
        return { schema, valueTypes, changed: false }
    }

    const nextValueTypes = valueTypes.slice()
    const usedApiNames = new Set(nextValueTypes.map(item => String(item?.apiName ?? '').toLowerCase()))
    const tableLabelById = new Map(
        schema.filter(item => item?.type === 'table').map(item => [item.id, item.label ?? ''])
    )

    let changed = false
    const nextSchema = schema.map((item) => {
        if (item?.type !== 'row' || item?.data?.valueTypeId) return item
        if (!isEnumSqlType(item?.data?.sqlType)) return item
        const values = parseEnumValues(item.data.sqlType)
        if (!values.length) return item

        const tableLabel = tableLabelById.get(item.parentNode) ?? ''
        let apiName = valueTypeApiNameFrom(tableLabel, item.label ?? '')
        let candidate = apiName
        let suffix = 2
        while (usedApiNames.has(candidate.toLowerCase())) {
            candidate = `${apiName}${suffix}`.slice(0, 100)
            suffix += 1
        }
        apiName = candidate
        usedApiNames.add(apiName.toLowerCase())

        const id = `inline-enum-${item.id}`
        nextValueTypes.push({
            id,
            apiName,
            displayName: valueTypeDisplayNameFrom(tableLabel, item.label ?? ''),
            description: '',
            version: '1.0.0',
            baseType: { type: 'string' },
            constraints: [{ id: `oneof-${item.id}`, type: 'oneOf', values, useIgnoreCase: false, failureMessage: '' }],
        })

        changed = true
        return { ...item, data: { ...item.data, valueTypeId: id, sqlType: 'STRING' } }
    })

    return changed
        ? { schema: nextSchema, valueTypes: nextValueTypes, changed }
        : { schema, valueTypes, changed: false }
}
