// Converts a Foundry dataset schema (Core.DatasetSchema.fieldSchemaList) into a
// JSON Schema draft-07 object so it can flow through the existing reference
// import pipeline. Shared by the Foundry browser modal (import) and the diagram
// sync action.

function mapScalar(type) {
    switch ((type || '').toUpperCase()) {
        case 'BOOLEAN': return { type: 'boolean' }
        case 'BYTE':
        case 'SHORT':
        case 'INTEGER': return { type: 'integer' }
        case 'LONG': return { type: 'integer', format: 'int64' }
        case 'FLOAT': return { type: 'number', format: 'float' }
        case 'DOUBLE': return { type: 'number', format: 'double' }
        case 'DECIMAL': return { type: 'number' }
        case 'DATE': return { type: 'string', format: 'date' }
        case 'TIMESTAMP': return { type: 'string', format: 'date-time' }
        case 'BINARY': return { type: 'string', contentEncoding: 'base64' }
        default: return { type: 'string' }
    }
}

function makeNullable(schema) {
    if (typeof schema.type === 'string') return { ...schema, type: [schema.type, 'null'] }
    if (Array.isArray(schema.type) && !schema.type.includes('null')) return { ...schema, type: [...schema.type, 'null'] }
    return schema
}

export function fieldToJsonSchema(field) {
    const type = (field?.type || '').toUpperCase()
    let schema
    if (type === 'ARRAY' && field.arraySubtype) {
        schema = { type: 'array', items: fieldToJsonSchema(field.arraySubtype) }
    } else if (type === 'STRUCT') {
        const properties = {}
        for (const sub of field.subSchemas || []) {
            if (sub?.name) properties[sub.name] = fieldToJsonSchema(sub)
        }
        schema = { type: 'object', properties }
    } else if (type === 'MAP') {
        schema = { type: 'object', additionalProperties: field.mapValueType ? fieldToJsonSchema(field.mapValueType) : true }
    } else {
        schema = mapScalar(type)
    }
    return field?.nullable ? makeNullable(schema) : schema
}

/**
 * @param {string} name dataset display name (used as the schema title)
 * @param {Array} fields Foundry DatasetFieldSchema[]
 */
export function datasetSchemaToJsonSchema(name, fields) {
    const properties = {}
    for (const field of fields || []) {
        if (field?.name) properties[field.name] = fieldToJsonSchema(field)
    }
    return {
        $schema: 'http://json-schema.org/draft-07/schema#',
        type: 'object',
        title: name,
        properties,
    }
}
