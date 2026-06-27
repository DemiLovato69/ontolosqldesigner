import { Position } from '@vue-flow/core'
import { normalizeRowPositions, uniqueElementId } from '@/services/SchemaRepair.js'

export const TABLE_STYLE = {
    display: 'flex',
    border: '1px solid #3d7a5c',
    background: '#3d7a5c',
    borderColor: '#3d7a5c',
    color: 'white',
    width: '400px',
    height: '40px',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderRadius: '8px 8px 0 0',
}

export const REFERENCE_TABLE_STYLE = {
    ...TABLE_STYLE,
    border: '1px dashed #8b5cf6',
    background: 'rgba(76, 63, 120, 0.25)',
    borderColor: '#8b5cf6',
}

export const ROW_STYLE = {
    display: 'flex',
    border: '1px solid var(--border-color)',
    borderColor: 'var(--border-color)',
    background: 'var(--bg-surface)',
    color: 'var(--text-primary)',
    width: '400px',
    height: '40px',
    alignItems: 'center',
    justifyContent: 'space-between',
}

export const REFERENCE_ROW_STYLE = {
    ...ROW_STYLE,
    borderColor: '#8b5cf6',
    background: 'color-mix(in srgb, #8b5cf6 10%, var(--bg-surface))',
}

const MARKER = {
    'one-to-one':   { markerStart: 'none',              markerEnd: 'none' },
    'one-to-many':  { markerStart: 'url(#chickenFoot)', markerEnd: 'none' },
    'many-to-one':  { markerStart: 'none',              markerEnd: 'url(#chickenFoot)' },
    'many-to-many': { markerStart: 'url(#chickenFoot)', markerEnd: 'url(#chickenFoot)' },
}

export function defaultIntType(dbType) {
    if (dbType === 'ontology') return 'STRING'
    if (dbType === 'postgresql' || dbType === 'sqlite') return 'INTEGER'
    if (dbType === 'oracle') return 'NUMBER'
    if (dbType === 'sqlserver') return 'INT'
    if (dbType === 'msaccess') return 'LONG'
    return 'INT(11)'
}

function uniqueName(name, existingNames) {
    if (!existingNames.includes(name)) return name

    const regex = new RegExp(`^${name}_(\\d+)$`)
    let suffix = 1

    existingNames.forEach(n => {
        const match = n.match(regex)
        if (match) {
            const num = parseInt(match[1], 10)
            if (num >= suffix) suffix = num + 1
        }
    })

    return `${name}_${suffix}`
}

function jsonSchemaTypes(type) {
    return Array.isArray(type) ? type : [type ?? 'string']
}

function nullableFromJsonSchema(property) {
    return jsonSchemaTypes(property?.type).includes('null')
}

function sqlTypeFromJsonSchema(property) {
    const type = jsonSchemaTypes(property?.type).find(item => item && item !== 'null') ?? 'string'
    if (type === 'integer') return 'INTEGER'
    if (type === 'number') return 'DOUBLE'
    if (type === 'boolean') return 'BOOLEAN'
    if (type === 'array') {
        const itemType = jsonSchemaTypes(property?.items?.type).find(item => item && item !== 'null') ?? 'string'
        return `ARRAY<${sqlTypeFromJsonSchema({ type: itemType })}>`
    }
    if (type === 'object') return 'STRUCT'
    return 'STRING'
}

function parseJsonReferenceSchemas(content, options = {}) {
    const decoded = typeof content === 'string' ? JSON.parse(content) : content
    const schemas = Array.isArray(decoded) ? decoded : [decoded]

    return schemas.map((jsonSchema, schemaIndex) => {
        if (!jsonSchema || typeof jsonSchema !== 'object' || jsonSchema.type !== 'object' || !jsonSchema.properties || typeof jsonSchema.properties !== 'object') {
            throw new Error('Reference JSON must be a JSON Schema object with properties.')
        }

        const fallbackTitle = jsonSchema.title || `ReferenceSchema${schemaIndex + 1}`
        const overrideTitle = typeof options.title === 'string' ? options.title.trim() : ''
        const title = (overrideTitle && schemas.length === 1) ? overrideTitle : fallbackTitle

        const properties = Object.entries(jsonSchema.properties).map(([propertyName, property]) => ({
            name: propertyName,
            jsonSchemaType: property?.type ?? 'string',
            jsonSchema: property,
            sqlType: sqlTypeFromJsonSchema(property),
            nullable: nullableFromJsonSchema(property),
            unsigned: false,
            description: property?.description ?? '',
        }))

        return {
            title,
            description: jsonSchema.description ?? null,
            referenceSource: { importedFrom: 'json-schema', schemaTitle: title, schema: jsonSchema.$schema ?? null },
            properties,
        }
    })
}

const POLARS_SCHEMA_RE = /(?:\bpl|\bpolars)\s*\.\s*Schema\s*\(/

function looksLikePolarsSchema(content) {
    return typeof content === 'string' && POLARS_SCHEMA_RE.test(content)
}

function skipPolarsWs(ctx) {
    while (ctx.pos < ctx.str.length) {
        const ch = ctx.str[ctx.pos]
        if (ch === ' ' || ch === '\t' || ch === '\n' || ch === '\r') { ctx.pos++; continue }
        if (ch === '#') { while (ctx.pos < ctx.str.length && ctx.str[ctx.pos] !== '\n') ctx.pos++; continue }
        break
    }
}

function expectPolarsChar(ctx, char) {
    if (ctx.str[ctx.pos] !== char) {
        throw new Error(`Could not parse Polars schema: expected "${char}".`)
    }
    ctx.pos++
}

function parsePolarsString(ctx) {
    const quote = ctx.str[ctx.pos]
    if (quote !== '"' && quote !== "'") {
        throw new Error('Could not parse Polars schema: expected a quoted field name.')
    }
    ctx.pos++
    let result = ''
    while (ctx.pos < ctx.str.length) {
        const ch = ctx.str[ctx.pos]
        if (ch === '\\') { result += ctx.str[ctx.pos + 1] ?? ''; ctx.pos += 2; continue }
        if (ch === quote) { ctx.pos++; return result }
        result += ch
        ctx.pos++
    }
    throw new Error('Could not parse Polars schema: unterminated string.')
}

// Consumes characters until the parenthesis/bracket/brace opened by the caller
// is balanced and closed. Assumes the matching opener was already consumed.
function skipPolarsToClose(ctx, depth = 1) {
    while (ctx.pos < ctx.str.length) {
        const ch = ctx.str[ctx.pos]
        if (ch === '"' || ch === "'") { parsePolarsString(ctx); continue }
        if (ch === '(' || ch === '[' || ch === '{') { depth++; ctx.pos++; continue }
        if (ch === ')' || ch === ']' || ch === '}') { depth--; ctx.pos++; if (depth === 0) return; continue }
        ctx.pos++
    }
    throw new Error('Could not parse Polars schema: unbalanced parentheses.')
}

function parsePolarsType(ctx) {
    skipPolarsWs(ctx)
    const nameMatch = /^(?:pl|polars)\s*\.\s*([A-Za-z_][A-Za-z0-9_]*)/.exec(ctx.str.slice(ctx.pos))
    if (!nameMatch) {
        throw new Error('Could not parse Polars schema: expected a pl.<Type> value.')
    }
    ctx.pos += nameMatch[0].length
    const typeName = nameMatch[1]
    skipPolarsWs(ctx)
    let args = null
    if (ctx.str[ctx.pos] === '(') {
        ctx.pos++
        args = parsePolarsTypeArgs(ctx, typeName)
    }
    return { typeName, args }
}

function parsePolarsTypeArgs(ctx, typeName) {
    skipPolarsWs(ctx)
    if (ctx.str[ctx.pos] === ')') { ctx.pos++; return null }

    if (typeName === 'Struct') {
        const fields = parsePolarsContainer(ctx)
        skipPolarsToClose(ctx)
        return { fields }
    }

    if (typeName === 'List' || typeName === 'Array') {
        const inner = parsePolarsType(ctx)
        skipPolarsToClose(ctx)
        return { inner }
    }

    // Parametric scalar types (Datetime, Decimal, Duration, ...) — ignore their args.
    skipPolarsToClose(ctx)
    return null
}

function parsePolarsEntry(ctx) {
    skipPolarsWs(ctx)
    if (ctx.str[ctx.pos] === '(') {
        ctx.pos++
        skipPolarsWs(ctx)
        const name = parsePolarsString(ctx)
        skipPolarsWs(ctx)
        expectPolarsChar(ctx, ',')
        const type = parsePolarsType(ctx)
        skipPolarsWs(ctx)
        expectPolarsChar(ctx, ')')
        return { name, type }
    }
    const name = parsePolarsString(ctx)
    skipPolarsWs(ctx)
    expectPolarsChar(ctx, ':')
    const type = parsePolarsType(ctx)
    return { name, type }
}

function parsePolarsContainer(ctx) {
    skipPolarsWs(ctx)
    const open = ctx.str[ctx.pos]
    const close = open === '{' ? '}' : open === '[' ? ']' : null
    if (!close) {
        throw new Error('Could not parse Polars schema: expected "{" for schema fields.')
    }
    ctx.pos++
    const entries = []
    skipPolarsWs(ctx)
    if (ctx.str[ctx.pos] === close) { ctx.pos++; return entries }

    while (true) {
        entries.push(parsePolarsEntry(ctx))
        skipPolarsWs(ctx)
        const ch = ctx.str[ctx.pos]
        if (ch === ',') {
            ctx.pos++
            skipPolarsWs(ctx)
            if (ctx.str[ctx.pos] === close) { ctx.pos++; break }
            continue
        }
        if (ch === close) { ctx.pos++; break }
        throw new Error(`Could not parse Polars schema: expected "," or "${close}".`)
    }
    return entries
}

function polarsTypeToProperty(type) {
    const name = type?.typeName
    switch (name) {
        case 'String':
        case 'Utf8':
        case 'Categorical':
        case 'Enum':
            return { jsonSchemaType: 'string', jsonSchema: { type: 'string' }, sqlType: 'STRING', unsigned: false }
        case 'Boolean':
            return { jsonSchemaType: 'boolean', jsonSchema: { type: 'boolean' }, sqlType: 'BOOLEAN', unsigned: false }
        case 'Int8':
        case 'Int16':
        case 'Int32':
            return { jsonSchemaType: 'integer', jsonSchema: { type: 'integer' }, sqlType: 'INTEGER', unsigned: false }
        case 'Int64':
            return { jsonSchemaType: 'integer', jsonSchema: { type: 'integer', format: 'int64' }, sqlType: 'BIGINT', unsigned: false }
        case 'UInt8':
        case 'UInt16':
        case 'UInt32':
            return { jsonSchemaType: 'integer', jsonSchema: { type: 'integer' }, sqlType: 'INTEGER', unsigned: true }
        case 'UInt64':
            return { jsonSchemaType: 'integer', jsonSchema: { type: 'integer', format: 'uint64' }, sqlType: 'BIGINT', unsigned: true }
        case 'Float32':
            return { jsonSchemaType: 'number', jsonSchema: { type: 'number', format: 'float' }, sqlType: 'FLOAT', unsigned: false }
        case 'Float64':
            return { jsonSchemaType: 'number', jsonSchema: { type: 'number', format: 'double' }, sqlType: 'DOUBLE', unsigned: false }
        case 'Decimal':
            return { jsonSchemaType: 'number', jsonSchema: { type: 'number' }, sqlType: 'DECIMAL', unsigned: false }
        case 'Date':
            return { jsonSchemaType: 'string', jsonSchema: { type: 'string', format: 'date' }, sqlType: 'DATE', unsigned: false }
        case 'Datetime':
            return { jsonSchemaType: 'string', jsonSchema: { type: 'string', format: 'date-time' }, sqlType: 'DATETIME', unsigned: false }
        case 'Time':
            return { jsonSchemaType: 'string', jsonSchema: { type: 'string', format: 'time' }, sqlType: 'TIME', unsigned: false }
        case 'Duration':
            return { jsonSchemaType: 'string', jsonSchema: { type: 'string' }, sqlType: 'STRING', unsigned: false }
        case 'Binary':
            return { jsonSchemaType: 'string', jsonSchema: { type: 'string', contentEncoding: 'base64' }, sqlType: 'BLOB', unsigned: false }
        case 'List':
        case 'Array': {
            const inner = type.args?.inner
                ? polarsTypeToProperty(type.args.inner)
                : { jsonSchemaType: 'string', jsonSchema: { type: 'string' }, sqlType: 'STRING' }
            return {
                jsonSchemaType: 'array',
                jsonSchema: { type: 'array', items: inner.jsonSchema },
                sqlType: `ARRAY<${inner.sqlType}>`,
                unsigned: false,
            }
        }
        case 'Struct': {
            const properties = {}
            for (const field of (type.args?.fields ?? [])) {
                properties[field.name] = polarsTypeToProperty(field.type).jsonSchema
            }
            return {
                jsonSchemaType: 'object',
                jsonSchema: { type: 'object', properties },
                sqlType: 'STRUCT',
                unsigned: false,
            }
        }
        default:
            return { jsonSchemaType: 'string', jsonSchema: { type: 'string' }, sqlType: 'STRING', unsigned: false }
    }
}

function parsePolarsSchema(content, options = {}) {
    const match = POLARS_SCHEMA_RE.exec(content)
    if (!match) {
        throw new Error('Could not parse Polars schema: expected pl.Schema({...}).')
    }

    const ctx = { str: content, pos: match.index + match[0].length }
    const entries = parsePolarsContainer(ctx)
    if (!entries.length) {
        throw new Error('Polars schema must contain at least one field.')
    }

    const overrideTitle = typeof options.title === 'string' ? options.title.trim() : ''
    const title = overrideTitle || 'PolarsSchema1'

    const properties = entries.map(entry => {
        const prop = polarsTypeToProperty(entry.type)
        return {
            name: entry.name,
            jsonSchemaType: prop.jsonSchemaType,
            jsonSchema: prop.jsonSchema,
            sqlType: prop.sqlType,
            nullable: false,
            unsigned: prop.unsigned ?? false,
            description: '',
        }
    })

    return [{
        title,
        description: null,
        referenceSource: { importedFrom: 'polars-schema', schemaTitle: title, schema: null },
        properties,
    }]
}

function parseReferenceSchemas(content, options = {}) {
    if (looksLikePolarsSchema(content)) {
        return parsePolarsSchema(content, options)
    }
    return parseJsonReferenceSchemas(content, options)
}

export const TableActions = {

    _nextZIndex(schema) {
        const max = schema.reduce((m, el) => (el.zIndex > m ? el.zIndex : m), 0)
        return max + 1
    },

    copyTable(schemaRef, tableId, position) {
        const schema = schemaRef.value
        const original = schema.find(el => el.id === tableId)
        if (!original) return null

        const existingTables = schema.filter(el => el.type === 'table')
        const newTableId = uniqueElementId(schema, 'table')
        const newLabel = uniqueName(original.label, existingTables.map(t => t.label))
        const zIndex = this._nextZIndex(schema)

        const children = schema.filter(el => el.parentNode === tableId && el.type === 'row')
        const newChildren = []
        for (const child of children) {
            newChildren.push({
                id: uniqueElementId([...schema, ...newChildren], 'row'),
                type: child.type,
                label: child.label,
                parentNode: newTableId,
                zIndex,
                position: { x: child.position.x, y: child.position.y },
                style: { ...child.style },
                draggable: child.draggable,
                data: {
                    ...child.data,
                    editing: false,
                    showModal: false,
                    showOptionsModal: false,
                },
            })
        }

        schemaRef.value = [...schema,
            {
                id: newTableId,
                type: original.type,
                label: newLabel,
                zIndex,
                data: { ...original.data },
                position,
                style: { ...original.style },
            },
            ...newChildren,
        ]

        schemaRef.value = normalizeRowPositions(schemaRef.value, new Set([newTableId])).schema

        return newTableId
    },

    addTable(schemaRef, name, position, dbType = 'mysql', color = '#3d7a5c', options = {}) {
        const schema = schemaRef.value
        const tableId = uniqueElementId(schema, 'table')
        const existingTables = schema.filter(el => el.type === 'table')
        const zIndex = this._nextZIndex(schema)

        const tableName = uniqueName(name, existingTables.map(t => t.label))
        const isReference = options.tableKind === 'reference' || options.reference === true

        schemaRef.value = [...schema, {
            id: tableId,
            type: 'table',
            label: tableName,
            zIndex,
            data: {
                toolbarPosition: Position.Top,
                toolbarVisible: true,
                color: isReference ? '#4c3f78' : color,
                description: '',
                titlePropertyRowId: null,
                ontologyActions: { create: false, modify: false, delete: false },
                editsEnabled: false,
                editsHistory: { enabled: false, storeAllPreviousProperties: false },
                tableKind: isReference ? 'reference' : 'object',
                reference: isReference,
                referenceSource: options.referenceSource ?? null,
            },
            position,
            style: isReference ? { ...REFERENCE_TABLE_STYLE } : { ...TABLE_STYLE, background: color, borderColor: color },
        }]

        this.addRow(schemaRef, { id: tableId, data: {} }, {
            rowName: 'id',
            keyMod: 'PRIMARY KEY',
            sqlType: defaultIntType(dbType),
            nullable: false,
            indexed: true,
            unsigned: false,
            reference: isReference,
        })

        return tableId
    },

    addReferenceTable(schemaRef, name, position) {
        return this.addTable(schemaRef, name, position, 'ontology', '#4c3f78', {
            tableKind: 'reference',
            reference: true,
            referenceSource: { importedFrom: 'manual' },
        })
    },

    importReferenceJsonSchemas(schemaRef, content, options = {}) {
        const referenceSchemas = parseReferenceSchemas(content, options)
        const changedTableIds = []

        for (const refSchema of referenceSchemas) {
            const title = refSchema.title
            let schema = schemaRef.value
            let table = schema.find(item => item.type === 'table'
                && (item.data?.tableKind === 'reference' || item.data?.reference)
                && (item.data?.referenceSource?.schemaTitle === title || item.label === title))

            if (!table) {
                const existingTables = schema.filter(el => el.type === 'table')
                const x = 80 + (existingTables.length % 4) * 440
                const y = 80 + Math.floor(existingTables.length / 4) * 360
                const tableId = uniqueElementId(schema, 'table')
                table = {
                    id: tableId,
                    type: 'table',
                    label: uniqueName(title, existingTables.map(t => t.label)),
                    zIndex: this._nextZIndex(schema),
                    data: {
                        toolbarPosition: Position.Top,
                        toolbarVisible: true,
                        color: '#4c3f78',
                        description: refSchema.description ?? '',
                        tableKind: 'reference',
                        reference: true,
                        referenceSource: { ...refSchema.referenceSource },
                        ontologyActions: { create: false, modify: false, delete: false },
                        editsHistory: { enabled: false, storeAllPreviousProperties: false },
                    },
                    position: { x, y },
                    style: { ...REFERENCE_TABLE_STYLE },
                }
                schemaRef.value = [...schema, table]
                schema = schemaRef.value
            } else {
                table.data = {
                    ...table.data,
                    tableKind: 'reference',
                    reference: true,
                    description: refSchema.description ?? table.data?.description ?? '',
                    referenceSource: { ...(table.data?.referenceSource ?? {}), ...refSchema.referenceSource },
                }
                table.style = { ...REFERENCE_TABLE_STYLE, width: table.style?.width ?? REFERENCE_TABLE_STYLE.width }
            }

            changedTableIds.push(table.id)
            const existingRows = schemaRef.value.filter(item => item.type === 'row' && item.parentNode === table.id)
            const existingByName = new Map(existingRows.map(row => [row.label, row]))
            let rowIndex = existingRows.length

            for (const property of refSchema.properties) {
                const row = existingByName.get(property.name)
                const rowData = {
                    reference: true,
                    jsonSchemaType: property.jsonSchemaType,
                    jsonSchema: property.jsonSchema,
                    keyMod: 'None',
                    sqlType: property.sqlType,
                    nullable: property.nullable,
                    indexed: true,
                    unsigned: property.unsigned ?? false,
                    defaultValue: '',
                    description: property.description ?? '',
                }
                if (row) {
                    row.data = { ...row.data, ...rowData }
                    row.style = { ...REFERENCE_ROW_STYLE, width: table.style?.width ?? REFERENCE_ROW_STYLE.width }
                    continue
                }

                schemaRef.value = [...schemaRef.value, {
                    id: uniqueElementId(schemaRef.value, 'row'),
                    type: 'row',
                    label: property.name,
                    zIndex: table.zIndex ?? 1,
                    position: { x: 0, y: 40 + 40 * rowIndex++ },
                    style: { ...REFERENCE_ROW_STYLE, width: table.style?.width ?? REFERENCE_ROW_STYLE.width },
                    draggable: false,
                    parentNode: table.id,
                    data: rowData,
                }]
            }
        }

        schemaRef.value = normalizeRowPositions(schemaRef.value, new Set(changedTableIds)).schema

        return changedTableIds
    },

    insertRowAfter(schemaRef, tableId, afterY, rowProps) {
        const schema = schemaRef.value
        const existingRows = schema.filter(el => el.parentNode === tableId && el.type === 'row')
        const tableNode = schema.find(el => el.id === tableId)
        const rowName = uniqueName(rowProps.rowName, existingRows.map(r => r.label))
        const id = uniqueElementId(schema, 'row')
        const rowBaseStyle = tableNode?.data?.reference ? REFERENCE_ROW_STYLE : ROW_STYLE
        const rowStyle = tableNode?.style?.width ? { ...rowBaseStyle, width: tableNode.style.width } : rowBaseStyle
        const newY = afterY + 40

        // Shift rows below the insertion point down by 40
        schema
            .filter(el => el.parentNode === tableId && el.type === 'row' && el.position.y > afterY)
            .forEach(row => { row.position.y += 40 })

        schemaRef.value = [...schema, {
            id,
            type: 'row',
            label: rowName,
            zIndex: tableNode?.zIndex ?? 1,
            position: { x: 0, y: newY },
            style: rowStyle,
            draggable: false,
            parentNode: tableId,
            data: {
                editing: false,
                showModal: false,
                showOptionsModal: false,
                keyMod: rowProps.keyMod,
                sqlType: rowProps.sqlType,
                nullable: rowProps.nullable,
                indexed: rowProps.indexed ?? true,
                unsigned: rowProps.unsigned,
                defaultValue: rowProps.defaultValue ?? '',
                description: rowProps.description ?? rowProps.comment ?? '',
                reference: rowProps.reference ?? tableNode?.data?.reference ?? false,
            }
        }]

        const button = schemaRef.value.find(el => el.type === 'add-row-button' && el.parentNode === tableId)
        if (button) button.position = { x: 0, y: 40 + 40 * schemaRef.value.filter(el => el.parentNode === tableId && el.type === 'row').length }
        schemaRef.value = normalizeRowPositions(schemaRef.value, new Set([tableId])).schema

        return id
    },

    addRow(schemaRef, nodeProps, rowProps) {
        const schema = schemaRef.value
        const existingRows = schema.filter(el => el.parentNode === nodeProps.id && el.type === 'row')
        const rowName = uniqueName(rowProps.rowName, existingRows.map(r => r.label))
        const id = uniqueElementId(schema, 'row')

        const tableNode = schema.find(el => el.id === nodeProps.id)
        const rowStyle = tableNode?.style?.width
            ? { ...(tableNode?.data?.reference ? REFERENCE_ROW_STYLE : ROW_STYLE), width: tableNode.style.width }
            : (tableNode?.data?.reference ? REFERENCE_ROW_STYLE : ROW_STYLE)

        const newRowY = 40 + 40 * existingRows.length
        schemaRef.value = [...schema, {
            id,
            type: 'row',
            label: rowName,
            zIndex: tableNode?.zIndex ?? 1,
            position: { x: 0, y: newRowY },
            style: rowStyle,
            draggable: false,
            parentNode: nodeProps.id,
            data: {
                editing: false,
                showModal: false,
                showOptionsModal: false,
                keyMod: rowProps.keyMod,
                sqlType: rowProps.sqlType,
                nullable: rowProps.nullable,
                indexed: rowProps.indexed ?? true,
                unsigned: rowProps.unsigned,
                defaultValue: rowProps.defaultValue ?? '',
                description: rowProps.description ?? rowProps.comment ?? '',
                reference: rowProps.reference ?? tableNode?.data?.reference ?? false,
            }
        }]

        const button = schemaRef.value.find(el => el.type === 'add-row-button' && el.parentNode === nodeProps.id)
        if (button) button.position = { x: 0, y: newRowY + 40 }
        schemaRef.value = normalizeRowPositions(schemaRef.value, new Set([nodeProps.id])).schema

        return id
    },

    createPivotTable(schemaRef, edge, dbType = 'mysql', color = '#3d7a5c') {
        const schema = schemaRef.value

        const sourceRow = schema.find(el => el.id === edge.source)
        const targetRow = schema.find(el => el.id === edge.target)
        if (!sourceRow || !targetRow) return null

        const sourceTable = schema.find(el => el.id === sourceRow.parentNode)
        const targetTable = schema.find(el => el.id === targetRow.parentNode)
        if (!sourceTable || !targetTable) return null

        const position = {
            x: (sourceTable.position.x + targetTable.position.x) / 2,
            y: Math.max(sourceTable.position.y, targetTable.position.y) + 200
        }

        const pivotName = `${sourceTable.label}_${targetTable.label}`
        const pivotTableId = this.addTable(schemaRef, pivotName, position, dbType, color)

        const sourceFkRowId = this.addRow(schemaRef, { id: pivotTableId, data: {} }, {
            rowName: `${sourceTable.label}_id`,
            keyMod: 'FOREIGN KEY',
            sqlType: defaultIntType(dbType),
            nullable: false,
            indexed: true,
            unsigned: false
        })

        const targetFkRowId = this.addRow(schemaRef, { id: pivotTableId, data: {} }, {
            rowName: `${targetTable.label}_id`,
            keyMod: 'FOREIGN KEY',
            sqlType: defaultIntType(dbType),
            nullable: false,
            indexed: true,
            unsigned: false
        })

        // Remove the original many-to-many edge
        schemaRef.value = schemaRef.value.filter(el => el.id !== edge.id)

        // Add two one-to-many edges: FK row (many) → original PK row (one)
        const edge1Id = uniqueElementId(schemaRef.value, 'edge')
        const edge2Id = uniqueElementId([...schemaRef.value, { id: edge1Id }], 'edge')
        schemaRef.value = [...schemaRef.value,
            {
                id: edge1Id,
                source: edge.source,
                target: sourceFkRowId,
                sourceHandle: 'source-left',
                targetHandle: 'target-right',
                type: 'chickenFoot',
                updatable: true,
                style: sourceTable.data?.color ? { stroke: sourceTable.data.color } : undefined,
                data: { relationshipType: 'many-to-one', color: sourceTable.data?.color, ...MARKER['many-to-one'] }
            },
            {
                id: edge2Id,
                source: edge.target,
                target: targetFkRowId,
                sourceHandle: 'source-right',
                targetHandle: 'target-left',
                type: 'chickenFoot',
                updatable: true,
                style: targetTable.data?.color ? { stroke: targetTable.data.color } : undefined,
                data: { relationshipType: 'many-to-one', color: targetTable.data?.color, ...MARKER['many-to-one'] }
            }
        ]

        return { pivotTableId, removedEdgeId: edge.id, addedEdgeIds: [edge1Id, edge2Id] }
    },

    updateConnectionLineType(schemaRef, selectedEdgeRef, relationshipType) {
        const schema = schemaRef.value
        const edgeIndex = schema.findIndex(el => el.id === selectedEdgeRef.value.id)
        if (edgeIndex === -1) return

        const edge = schema[edgeIndex]
        edge.type = 'chickenFoot'
        Object.assign(edge.data, { relationshipType, ...MARKER[relationshipType] })
    },

    deleteEdge(schemaRef, selectedEdgeRef) {
        schemaRef.value = schemaRef.value.filter(el => el.id !== selectedEdgeRef.value.id)
    },

    deleteNode(schemaRef, nodeId) {
        const schema = schemaRef.value
        const nodeToDelete = schema.find(el => el.id === nodeId)
        if (!nodeToDelete) return
        const connectedEdgeIds = new Set(schema
            .filter(el => el.source === nodeId || el.target === nodeId)
            .map(el => el.id))

        if (nodeToDelete.type === 'table') {
            const childIds = new Set(schema.filter(el => el.parentNode === nodeId).map(el => el.id))
            schemaRef.value = schema.filter(el => el.id !== nodeId && el.parentNode !== nodeId && !childIds.has(el.source) && !childIds.has(el.target) && !connectedEdgeIds.has(el.id))
        } else if (nodeToDelete.type === 'row') {
            schemaRef.value = schema.filter(el => el.id !== nodeId && el.source !== nodeId && el.target !== nodeId)
            schema
                .filter(el => el.parentNode === nodeToDelete.parentNode && el.type === 'row' && el.position.y > nodeToDelete.position.y)
                .sort((a, b) => a.position.y - b.position.y)
                .forEach((row, index) => { row.position.y = nodeToDelete.position.y + 40 * index })

            const remainingRows = schemaRef.value.filter(el => el.parentNode === nodeToDelete.parentNode && el.type === 'row')
            const button = schemaRef.value.find(el => el.type === 'add-row-button' && el.parentNode === nodeToDelete.parentNode)
            if (button) button.position = { x: 0, y: 40 + 40 * remainingRows.length }
        } else if (nodeToDelete.type === 'pipeline-transform') {
            schemaRef.value = schema.filter(el => el.id !== nodeId && el.source !== nodeId && el.target !== nodeId)
        }
    }
}
