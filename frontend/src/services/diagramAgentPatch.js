import { TableActions, defaultIntType } from '@/services/TableActions.js'
import { canvasTypeForValueType } from '@/services/valueTypes.js'

// Applies a validated agent patch (the allowlisted operations returned by the
// backend) to the live diagram refs, reusing TableActions so the result matches
// manual edits. Returns applied/failed summaries plus a schema diff the caller
// can broadcast to collaborators via whisper('schema-patch', ...).

const uuid = () =>
    typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `id-${Math.random().toString(36).slice(2)}`

const DESTRUCTIVE = new Set(['delete_table', 'delete_column', 'delete_relationship', 'rename_table', 'rename_column'])

export function isDestructive(op) {
    return DESTRUCTIVE.has(op?.op)
}

// Short human-readable label for the preview list.
export function operationLabel(op) {
    switch (op?.op) {
        case 'add_table': return `Add table “${op.name}”${op.columns?.length ? ` (${op.columns.length} columns)` : ''}`
        case 'add_reference_table': return `Add reference table “${op.name}”`
        case 'update_table': return `Update table “${op.table}”`
        case 'rename_table': return `Rename table “${op.table}” → “${op.name}”`
        case 'delete_table': return `Delete table “${op.table}”`
        case 'add_column': return `Add column “${op.name}” to “${op.table}”`
        case 'update_column': return `Update column “${op.column}” on “${op.table}”`
        case 'rename_column': return `Rename column “${op.column}” → “${op.name}” on “${op.table}”`
        case 'delete_column': return `Delete column “${op.column}” from “${op.table}”`
        case 'add_relationship': return `Link ${endpointLabel(op.from)} → ${endpointLabel(op.to)}`
        case 'add_value_type': return `Add value type “${op.name}”`
        case 'update_value_type': return `Update value type “${op.name}”`
        case 'add_shared_property_type': return `Add shared property type “${op.name}”`
        case 'add_interface': return `Add interface “${op.name}”`
        case 'update_interface': return `Update interface “${op.name}”`
        case 'add_interface_link_constraint': return `Add interface link constraint`
        case 'add_custom_action': return `Add action “${op.name}”`
        default: return op?.op ?? 'Unknown operation'
    }
}

function endpointLabel(ep) {
    if (!ep) return '?'
    if (typeof ep === 'string') return ep
    return ep.column ? `${ep.table}.${ep.column}` : ep.table
}

function keyModFor(key) {
    const k = String(key ?? '').toUpperCase()
    if (k === 'PK' || k === 'PRIMARY KEY' || k === 'PRIMARY') return 'PRIMARY KEY'
    if (k === 'FK' || k === 'FOREIGN KEY' || k === 'FOREIGN') return 'FOREIGN KEY'
    return 'None'
}

function baseTypeFor(type) {
    const known = ['string', 'integer', 'double', 'boolean', 'date', 'timestamp', 'decimal', 'array', 'struct', 'long', 'float', 'short']
    const t = String(type ?? '').toLowerCase()
    // Strip width/precision so an sqlType like "VARCHAR(255)" maps to a base type.
    const base = t.replace(/<.*>$/, '').replace(/\(.*\)$/, '').trim()
    const alias = { varchar: 'string', text: 'string', int: 'integer', bigint: 'long', number: 'decimal', datetime: 'timestamp', bool: 'boolean' }
    const resolved = alias[base] ?? base
    return { type: known.includes(resolved) ? resolved : 'string' }
}

// Canonical value-type shape expected by the Value Types modal and exporters.
function makeValueType({ name, displayName, type, description }) {
    return {
        id: uuid(),
        apiName: name,
        displayName: displayName || name,
        description: description || '',
        version: '1.0.0',
        baseType: baseTypeFor(type),
        constraints: [],
    }
}

export function applyAgentPatch(operations, ctx) {
    const {
        schema, valueTypes, interfaces, interfaceLinkConstraints, customActions, sharedPropertyTypes,
        diagramDbType, defaultTableColor,
    } = ctx
    const dbType = diagramDbType?.value ?? 'ontology'

    const applied = []
    const failed = []
    const warnings = []
    let mutatedInPlace = false

    const beforeIds = new Set(schema.value.map(el => el.id))
    const beforeJSON = new Map(schema.value.map(el => [el.id, JSON.stringify(el)]))

    const tables = () => schema.value.filter(el => el.type === 'table')
    const findTable = (ref) => {
        if (ref == null) return null
        const key = String(ref).toLowerCase()
        return schema.value.find(el => el.type === 'table' && (el.id === ref || (el.label ?? '').toLowerCase() === key)) ?? null
    }
    const findRow = (table, ref) => {
        if (!table || ref == null) return null
        const key = String(ref).toLowerCase()
        return schema.value.find(el => el.type === 'row' && el.parentNode === table.id
            && (el.id === ref || (el.label ?? '').toLowerCase() === key)) ?? null
    }
    const nextPosition = () => {
        const count = tables().length
        return { x: 80 + (count % 4) * 440, y: 80 + Math.floor(count / 4) * 360 }
    }
    const resolveEndpoint = (ep) => {
        if (typeof ep === 'string') {
            const direct = schema.value.find(el => el.type === 'row' && el.id === ep)
            if (direct) return direct.id
            if (ep.includes('.')) {
                const [t, c] = ep.split('.')
                const row = findRow(findTable(t), c)
                return row?.id ?? null
            }
            return null
        }
        const row = findRow(findTable(ep?.table), ep?.column)
        return row?.id ?? null
    }
    // Find a value type by id, apiName, or displayName (case-insensitive).
    const resolveValueType = (ref) => {
        if (ref == null || ref === '') return null
        const key = String(ref).toLowerCase()
        return (valueTypes.value ?? []).find(v =>
            v.id === ref
            || (v.apiName ?? '').toLowerCase() === key
            || (v.displayName ?? '').toLowerCase() === key
        ) ?? null
    }

    // Resolve a value type, creating it if the model only referenced it by name.
    const ensureValueType = (ref, opts = {}) => {
        const found = resolveValueType(ref)
        if (found) return found
        const created = makeValueType({ name: String(ref), displayName: opts.displayName, type: opts.type, description: opts.description })
        valueTypes.value = [...(valueTypes.value ?? []), created]
        return created
    }

    // Link a row to a value type the way the app expects: data.valueTypeId plus a
    // derived sqlType. (Rows reference value types by id, not by name.)
    const linkRowValueType = (rowId, ref, fallbackType) => {
        if (!ref) return
        const vt = ensureValueType(ref, { type: fallbackType })
        const row = schema.value.find(el => el.id === rowId)
        if (!row) return
        row.data = {
            ...row.data,
            valueTypeId: vt.id,
            sqlType: canvasTypeForValueType(vt),
            ontologyBaseType: null,
            ontologyImportedSqlType: null,
        }
        mutatedInPlace = true
    }

    const addColumn = (tableId, col) => {
        const existing = schema.value
            .filter(el => el.type === 'row' && el.parentNode === tableId)
            .map(r => (r.label ?? '').toLowerCase())
        if (existing.includes(String(col.name).toLowerCase())) {
            warnings.push(`Column “${col.name}” already exists; skipped.`)
            return
        }
        const rowId = TableActions.addRow(schema, { id: tableId, data: {} }, {
            rowName: col.name,
            keyMod: keyModFor(col.key ?? col.keyMod),
            sqlType: col.sqlType || defaultIntType(dbType),
            nullable: !!col.nullable,
            indexed: col.indexed ?? true,
            unsigned: false,
        })
        if (col.valueType) linkRowValueType(rowId, col.valueType, col.sqlType)
    }

    const handlers = {
        add_table: (op) => {
            const id = TableActions.addTable(schema, op.name, nextPosition(), dbType, op.color || defaultTableColor?.value)
            for (const col of Array.isArray(op.columns) ? op.columns : []) {
                if (col?.name) addColumn(id, col)
            }
        },
        add_reference_table: (op) => {
            const id = TableActions.addReferenceTable(schema, op.name, nextPosition())
            for (const col of Array.isArray(op.columns) ? op.columns : []) {
                if (col?.name) addColumn(id, col)
            }
        },
        add_column: (op) => {
            const table = findTable(op.table)
            if (!table) throw new Error(`Table “${op.table}” not found.`)
            addColumn(table.id, op)
        },
        update_table: (op) => {
            const table = findTable(op.table)
            if (!table) throw new Error(`Table “${op.table}” not found.`)
            if (op.name) table.label = op.name
            if (op.color && !table.data?.reference) {
                table.style = { ...table.style, background: op.color, borderColor: op.color, border: `1px solid ${op.color}` }
                table.data = { ...table.data, color: op.color }
            }
            mutatedInPlace = true
        },
        rename_table: (op) => {
            const table = findTable(op.table)
            if (!table) throw new Error(`Table “${op.table}” not found.`)
            table.label = op.name
            mutatedInPlace = true
        },
        delete_table: (op) => {
            const table = findTable(op.table)
            if (!table) throw new Error(`Table “${op.table}” not found.`)
            TableActions.deleteNode(schema, table.id)
        },
        update_column: (op) => {
            const table = findTable(op.table)
            const row = findRow(table, op.column)
            if (!row) throw new Error(`Column “${op.column}” not found on “${op.table}”.`)
            if (op.name) row.label = op.name
            const data = { ...row.data }
            if (op.sqlType) data.sqlType = op.sqlType
            if (op.key !== undefined) data.keyMod = keyModFor(op.key)
            if (op.nullable !== undefined) data.nullable = !!op.nullable
            if (op.indexed !== undefined) data.indexed = !!op.indexed
            row.data = data
            if (op.valueType) linkRowValueType(row.id, op.valueType, op.sqlType)
            mutatedInPlace = true
        },
        rename_column: (op) => {
            const row = findRow(findTable(op.table), op.column)
            if (!row) throw new Error(`Column “${op.column}” not found on “${op.table}”.`)
            row.label = op.name
            mutatedInPlace = true
        },
        delete_column: (op) => {
            const row = findRow(findTable(op.table), op.column)
            if (!row) throw new Error(`Column “${op.column}” not found on “${op.table}”.`)
            TableActions.deleteNode(schema, row.id)
        },
        add_relationship: (op) => {
            const from = resolveEndpoint(op.from)
            const to = resolveEndpoint(op.to)
            if (!from || !to) throw new Error('Could not resolve relationship endpoints.')
            const id = TableActions.addRelationshipEdge(schema, from, to, { cardinality: op.cardinality })
            if (!id) throw new Error('Could not create the relationship.')
        },
        add_value_type: (op) => {
            const existing = resolveValueType(op.name)
            if (existing) {
                if (op.displayName) existing.displayName = op.displayName
                if (op.description !== undefined) existing.description = op.description
                if (op.type) existing.baseType = baseTypeFor(op.type)
                if (!existing.version) existing.version = '1.0.0'
                valueTypes.value = [...valueTypes.value]
                return
            }
            valueTypes.value = [...(valueTypes.value ?? []), makeValueType({
                name: op.name,
                displayName: op.displayName,
                type: op.type,
                description: op.description,
            })]
        },
        update_value_type: (op) => {
            const item = resolveValueType(op.name)
            if (!item) throw new Error(`Value type “${op.name}” not found.`)
            if (op.displayName) item.displayName = op.displayName
            if (op.description !== undefined) item.description = op.description
            if (op.type) item.baseType = baseTypeFor(op.type)
            if (!item.version) item.version = '1.0.0'
            valueTypes.value = [...valueTypes.value]
        },
        add_shared_property_type: (op) => {
            sharedPropertyTypes.value = [...(sharedPropertyTypes.value ?? []), {
                id: uuid(),
                apiName: op.name,
                displayName: op.displayName || op.name,
                baseType: baseTypeFor(op.type),
                description: op.description || '',
            }]
        },
        add_interface: (op) => {
            interfaces.value = [...(interfaces.value ?? []), {
                id: uuid(),
                apiName: op.name,
                displayName: op.displayName || op.name,
                kind: 'interface',
                description: op.description || '',
                extendsInterfaces: [],
                properties: (Array.isArray(op.properties) ? op.properties : []).map(p => ({
                    id: uuid(),
                    apiName: p.apiName || p.name || 'property',
                    displayName: p.displayName || p.apiName || p.name || 'property',
                    type: p.type || 'string',
                })),
            }]
        },
        update_interface: (op) => {
            const item = (interfaces.value ?? []).find(i => (i.apiName ?? '').toLowerCase() === String(op.name).toLowerCase())
            if (!item) throw new Error(`Interface “${op.name}” not found.`)
            if (op.displayName) item.displayName = op.displayName
            if (op.description !== undefined) item.description = op.description
            interfaces.value = [...interfaces.value]
        },
        add_interface_link_constraint: (op) => {
            interfaceLinkConstraints.value = [...(interfaceLinkConstraints.value ?? []), {
                id: uuid(),
                apiName: op.name || `${endpointLabel(op.from)}_link`,
                displayName: op.displayName || op.name || 'Link constraint',
                from: typeof op.from === 'string' ? op.from : (op.from?.table ?? ''),
                to: typeof op.to === 'string' ? op.to : (op.to?.table ?? ''),
                cardinality: op.cardinality || 'many-to-one',
            }]
        },
        add_custom_action: (op) => {
            customActions.value = [...(customActions.value ?? []), {
                id: uuid(),
                apiName: op.name,
                displayName: op.displayName || op.name,
                description: op.description || '',
                actionType: 'rules',
                parameters: [],
                rules: [],
            }]
        },
    }

    for (const op of Array.isArray(operations) ? operations : []) {
        const handler = handlers[op?.op]
        if (!handler) {
            failed.push({ op: op?.op ?? 'unknown', message: 'Unsupported operation.' })
            continue
        }
        try {
            handler(op)
            applied.push(operationLabel(op))
        } catch (error) {
            failed.push({ op: op.op, message: error?.message ?? 'Failed to apply.' })
        }
    }

    if (mutatedInPlace) {
        schema.value = [...schema.value]
    }

    const added = schema.value.filter(el => !beforeIds.has(el.id))
    const updated = schema.value.filter(el => beforeIds.has(el.id) && beforeJSON.get(el.id) !== JSON.stringify(el))
    const removed = [...beforeIds].filter(id => !schema.value.some(el => el.id === id))

    return { applied, failed, warnings, diff: { added, updated, removed } }
}
