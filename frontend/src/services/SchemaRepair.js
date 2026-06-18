export function uniqueElementId(schema = [], prefix = 'element') {
    const existingIds = new Set((schema ?? []).map(item => item?.id).filter(Boolean))

    for (let attempt = 0; attempt < 20; attempt++) {
        const randomPart = globalThis.crypto?.randomUUID?.()
            ?? `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`
        const id = `${prefix}-${randomPart}`
        if (!existingIds.has(id)) return id
    }

    let suffix = existingIds.size + 1
    while (existingIds.has(`${prefix}-${suffix}`)) suffix++

    return `${prefix}-${suffix}`
}

export function normalizeRowPositions(schema = [], tableIds = null) {
    const targetTableIds = tableIds ? new Set([...tableIds].filter(Boolean)) : null
    const rowsByTable = new Map()
    const originalIndex = new Map()

    schema.forEach((item, index) => {
        originalIndex.set(item, index)
        if (item?.type !== 'row' || !item.parentNode) return
        if (targetTableIds && !targetTableIds.has(item.parentNode)) return
        if (!rowsByTable.has(item.parentNode)) rowsByTable.set(item.parentNode, [])
        rowsByTable.get(item.parentNode).push(item)
    })

    let changed = false
    const updatesById = new Map()

    for (const rows of rowsByTable.values()) {
        rows.sort((a, b) => {
            const yDiff = (a.position?.y ?? 0) - (b.position?.y ?? 0)
            return yDiff || ((originalIndex.get(a) ?? 0) - (originalIndex.get(b) ?? 0))
        })

        rows.forEach((row, index) => {
            const nextPosition = { x: 0, y: 40 + (index * 40) }
            if (row.position?.x !== nextPosition.x || row.position?.y !== nextPosition.y) {
                changed = true
                updatesById.set(row.id, nextPosition)
            }
        })
    }

    if (!changed) return { schema, changed: false, updatedTableIds: [] }

    return {
        schema: schema.map(item => updatesById.has(item?.id)
            ? { ...item, position: updatesById.get(item.id) }
            : item),
        changed: true,
        updatedTableIds: [...rowsByTable.keys()],
    }
}

export function repairDuplicateSchemaIds(schema = []) {
    const seenIds = new Set()
    const changedTableIds = new Set()
    let changed = false
    let nextSchema = schema.map(item => {
        if (!item || typeof item !== 'object') return item

        const currentId = typeof item.id === 'string' && item.id !== '' ? item.id : null
        if (currentId && !seenIds.has(currentId)) {
            seenIds.add(currentId)
            return item
        }

        changed = true
        const nextId = uniqueElementId([...schema, ...[...seenIds].map(id => ({ id }))], item.type || 'element')
        seenIds.add(nextId)
        if (item.type === 'row' && item.parentNode) changedTableIds.add(item.parentNode)

        return { ...item, id: nextId }
    })

    if (changed) {
        const normalized = normalizeRowPositions(nextSchema, changedTableIds)
        nextSchema = normalized.schema
    }

    return { schema: nextSchema, changed, changedTableIds: [...changedTableIds] }
}

export function repairAndNormalizeSchema(schema = [], tableIds = null) {
    const repaired = repairDuplicateSchemaIds(schema)
    const normalized = normalizeRowPositions(repaired.schema, tableIds)

    return {
        schema: normalized.schema,
        changed: repaired.changed || normalized.changed,
        changedTableIds: [...new Set([...(repaired.changedTableIds ?? []), ...(normalized.updatedTableIds ?? [])])],
    }
}
