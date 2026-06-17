import { ref, nextTick } from 'vue'
import { TableActions, defaultIntType } from '@/services/TableActions.js'

export function useSchemaActions({ schema, isSaved, whisper, diagramDbType, addEdges, updateEdge, findNode, screenToFlowCoordinate, flowToScreenCoordinate, snapshot, logAction = () => {}, defaultTableColor, defaultConnectionColor }) {
    const isPlacingTable = ref(false)
    const isPlacingReferenceTable = ref(false)
    const isConnecting = ref(false)
    const copyingTableId = ref(null)
    const selectedEdge = ref(null)
    const selectedRowIds = ref([])
    const showRelationshipModal = ref(false)
    const modalPosition = ref({ x: 0, y: 0 })

    // --- Add/copy/place table ---

    const addTable = () => {
        copyingTableId.value = null
        isPlacingReferenceTable.value = false
        isPlacingTable.value = true
    }

    const addReferenceTable = () => {
        copyingTableId.value = null
        isPlacingReferenceTable.value = true
        isPlacingTable.value = true
    }

    const copyTable = (tableId) => {
        copyingTableId.value = tableId
        isPlacingTable.value = true
    }

    const onPaneClick = (event) => {
        if (!isPlacingTable.value) return
        snapshot()
        isPlacingTable.value = false
        const position = screenToFlowCoordinate({ x: event.clientX, y: event.clientY })
        let tableId
        const sourceTableId = copyingTableId.value
        const sourceTable = sourceTableId ? schema.value.find(el => el.id === sourceTableId) : null
        if (sourceTableId) {
            tableId = TableActions.copyTable(schema, sourceTableId, position)
            copyingTableId.value = null
        } else if (isPlacingReferenceTable.value) {
            tableId = TableActions.addReferenceTable(schema, 'ReferenceTable', position)
            isPlacingReferenceTable.value = false
        } else {
            tableId = TableActions.addTable(schema, 'new_table', position, diagramDbType.value, defaultTableColor?.value)
        }
        isSaved.value = false
        if (sourceTableId) {
            logAction('table_copied', { table_name: sourceTable?.label })
        } else if (schema.value.find(el => el.id === tableId)?.data?.reference) {
            const tableNode = schema.value.find(el => el.id === tableId)
            logAction('reference_table_created', { table_name: tableNode?.label })
        } else {
            const tableNode = schema.value.find(el => el.id === tableId)
            logAction('table_created', { table_name: tableNode?.label })
        }
        const nodes = schema.value.filter(el => el.id === tableId || el.parentNode === tableId)
        whisper('schema-patch', { add: nodes })
    }

    const importReferenceJsonSchemas = (content) => {
        snapshot()
        const beforeIds = new Set(schema.value.map(item => item.id))
        const beforeItems = new Map(schema.value.map(item => [item.id, JSON.stringify(item)]))
        const tableIds = TableActions.importReferenceJsonSchemas(schema, content)
        const added = schema.value.filter(item => !beforeIds.has(item.id))
        const updated = schema.value.filter(item => beforeIds.has(item.id) && beforeItems.get(item.id) !== JSON.stringify(item))
        isSaved.value = false
        const importedTables = tableIds.map(id => schema.value.find(item => item.id === id)?.label).filter(Boolean)
        logAction('reference_table_imported', { tables: importedTables, count: tableIds.length })
        whisper('schema-patch', { add: added, update: updated.map(item => ({ id: item.id, data: item.data, style: item.style, label: item.label })) })
        return tableIds
    }

    // --- Row operations ---

    const addRow = (nodeProps) => {
        snapshot()
        const parentTable = schema.value.find(el => el.id === nodeProps.id)
        const rowId = TableActions.addRow(schema, nodeProps, {
            rowName: 'new_row',
            keyMod: 'None',
            sqlType: defaultIntType(diagramDbType.value),
            nullable: false,
            indexed: true,
            unsigned: false,
        })
        isSaved.value = false
        logAction('column_added', { table_name: parentTable?.label })
        const newRow = schema.value.find(el => el.id === rowId)
        const button = schema.value.find(el => el.type === 'add-row-button' && el.parentNode === nodeProps.id)
        const patch = { add: [newRow] }
        if (button) patch.update = [{ id: button.id, position: button.position }]
        whisper('schema-patch', patch)
    }

    const addRowAfter = (rowId) => {
        const row = schema.value.find(el => el.id === rowId)
        if (!row) return
        snapshot()
        const parentTable = schema.value.find(el => el.id === row.parentNode)
        const shiftedRows = schema.value.filter(el => el.parentNode === row.parentNode && el.type === 'row' && el.position.y > row.position.y)
        const newId = TableActions.insertRowAfter(schema, row.parentNode, row.position.y, {
            rowName: 'new_row',
            keyMod: 'None',
            sqlType: defaultIntType(diagramDbType.value),
            nullable: false,
            indexed: true,
            unsigned: false,
        })
        isSaved.value = false
        logAction('column_added', { table_name: parentTable?.label })
        const newRow = schema.value.find(el => el.id === newId)
        const button = schema.value.find(el => el.type === 'add-row-button' && el.parentNode === row.parentNode)
        const updatedShifted = shiftedRows.map(el => schema.value.find(s => s.id === el.id)).filter(Boolean).map(el => ({ id: el.id, position: el.position }))
        const patch = { add: [newRow], update: updatedShifted }
        if (button) patch.update = [...(patch.update ?? []), { id: button.id, position: button.position }]
        whisper('schema-patch', patch)
    }

    const uniqueIds = (ids) => [...new Set(ids.filter(Boolean))]

    const numericWidth = (value) => {
        const parsed = Number.parseFloat(String(value ?? '').replace('px', ''))
        return Number.isFinite(parsed) ? parsed : 0
    }

    const nodeCenterX = (node) => {
        const flowNode = findNode(node.id)
        const x = flowNode?.computedPosition?.x ?? node.position?.x ?? 0
        const width = flowNode?.dimensions?.width || numericWidth(node.style?.width) || 190
        return x + (width / 2)
    }

    const rowParentTableCenterX = (row) => {
        const table = schema.value.find(el => el.id === row.parentNode)
        if (!table) return nodeCenterX(row)
        const tableNode = findNode(table.id)
        const tableX = tableNode?.computedPosition?.x ?? table.position?.x ?? 0
        const tableWidth = tableNode?.dimensions?.width || numericWidth(table.style?.width) || numericWidth(row.style?.width) || 336
        return tableX + (tableWidth / 2)
    }

    const rowSourceHandleFacing = (row, targetCenterX) => targetCenterX >= rowParentTableCenterX(row) ? 'source-right' : 'source-left'
    const rowTargetHandleFacing = (row, sourceCenterX) => sourceCenterX <= rowParentTableCenterX(row) ? 'target-left' : 'target-right'
    const isReferenceRow = (row) => !!schema.value.find(el => el.id === row.parentNode)?.data?.reference
    const colorWithAlpha = (color, alpha) => {
        if (!/^#[0-9a-f]{6}$/i.test(color ?? '')) return color
        const r = Number.parseInt(color.slice(1, 3), 16)
        const g = Number.parseInt(color.slice(3, 5), 16)
        const b = Number.parseInt(color.slice(5, 7), 16)
        return `rgba(${r}, ${g}, ${b}, ${alpha})`
    }

    const deleteEdge = () => {
        snapshot()
        const edgeId = selectedEdge.value.id
        const srcRow = schema.value.find(el => el.id === selectedEdge.value.source)
        const tgtRow = schema.value.find(el => el.id === selectedEdge.value.target)
        const srcTable = schema.value.find(el => el.id === srcRow?.parentNode)
        const tgtTable = schema.value.find(el => el.id === tgtRow?.parentNode)
        const isReferenceLink = selectedEdge.value.data?.linkKind === 'reference'
        const isTransformLink = selectedEdge.value.data?.linkKind === 'transform'
        const transformNode = isTransformLink
            ? schema.value.find(el => (el.id === selectedEdge.value.source || el.id === selectedEdge.value.target) && el.type === 'pipeline-transform')
            : null
        if (transformNode) {
            transformNode.data = {
                ...transformNode.data,
                sourceRowIds: (transformNode.data?.sourceRowIds ?? []).filter(id => id !== selectedEdge.value.source),
                targetRowIds: (transformNode.data?.targetRowIds ?? []).filter(id => id !== selectedEdge.value.target),
            }
        }
        TableActions.deleteEdge(schema, selectedEdge)
        showRelationshipModal.value = false
        isSaved.value = false
        logAction(isTransformLink ? 'pipeline_transform_changed' : (isReferenceLink ? 'reference_link_deleted' : 'connection_deleted'), { from_table: srcTable?.label, from_column: srcRow?.label, to_table: tgtTable?.label, to_column: tgtRow?.label })
        whisper('schema-patch', { remove: [edgeId], update: transformNode ? [{ id: transformNode.id, data: { ...transformNode.data } }] : [] })
    }

    const deleteNode = (nodeId) => {
        snapshot()
        const nodeToDelete = schema.value.find(el => el.id === nodeId)
        const childIds = nodeToDelete?.type === 'table'
            ? schema.value.filter(el => el.parentNode === nodeId).map(el => el.id)
            : []
        const connectedEdgeIds = schema.value
            .filter(el => el.source === nodeId || el.target === nodeId || childIds.includes(el.source) || childIds.includes(el.target))
            .map(el => el.id)
        const affectedSiblingIds = nodeToDelete?.type === 'row'
            ? [
                ...schema.value.filter(el => el.parentNode === nodeToDelete.parentNode && el.type === 'row' && el.position.y > nodeToDelete.position.y).map(el => el.id),
                ...schema.value.filter(el => el.type === 'add-row-button' && el.parentNode === nodeToDelete.parentNode).map(el => el.id),
              ]
            : []

        if (nodeToDelete?.type === 'table') {
            logAction(nodeToDelete.data?.reference ? 'reference_table_deleted' : 'table_deleted', { table_name: nodeToDelete.label })
        } else if (nodeToDelete?.type === 'pipeline-transform') {
            logAction('pipeline_transform_deleted', { transform_name: nodeToDelete.label })
        } else if (nodeToDelete?.type === 'row') {
            const parentTable = schema.value.find(el => el.id === nodeToDelete.parentNode)
            logAction('column_deleted', { table_name: parentTable?.label, column_name: nodeToDelete.label })
        }

        TableActions.deleteNode(schema, nodeId)
        isSaved.value = false

        const updates = affectedSiblingIds
            .map(id => schema.value.find(el => el.id === id))
            .filter(Boolean)
            .map(el => ({ id: el.id, position: el.position }))
        whisper('schema-patch', { remove: [nodeId, ...childIds, ...connectedEdgeIds], update: updates })
        selectedRowIds.value = selectedRowIds.value.filter(id => id !== nodeId && !childIds.includes(id))
    }

    const onConnect = (params) => {
        snapshot()
        params.updatable = true
        const srcRow = schema.value.find(el => el.id === params.source)
        const tgtRow = schema.value.find(el => el.id === params.target)
        const srcTransform = schema.value.find(el => el.id === params.source && el.type === 'pipeline-transform')
        const tgtTransform = schema.value.find(el => el.id === params.target && el.type === 'pipeline-transform')
        const srcTable = schema.value.find(el => el.id === srcRow?.parentNode)
        const tgtTable = schema.value.find(el => el.id === tgtRow?.parentNode)
        const transformNode = srcTransform || tgtTransform
        if (transformNode && (srcRow || tgtRow)) {
            const row = srcRow || tgtRow
            const isInput = isReferenceRow(row)
            const transformCenterX = nodeCenterX(transformNode)
            const transformEdge = {
                id: `transform-edge-${Math.random().toString(36).slice(2)}`,
                type: 'transform',
                source: isInput ? row.id : transformNode.id,
                target: isInput ? transformNode.id : row.id,
                sourceHandle: isInput ? rowSourceHandleFacing(row, transformCenterX) : 'source-right',
                targetHandle: isInput ? 'target-left' : rowTargetHandleFacing(row, transformCenterX),
                updatable: true,
                style: { stroke: '#f59e0b', strokeDasharray: '4 4' },
                data: { ...(params.data ?? {}), linkKind: 'transform', exportable: false, direction: isInput ? 'input' : 'output' },
            }
            if (isInput) {
                transformNode.data = { ...transformNode.data, sourceRowIds: uniqueIds([...(transformNode.data?.sourceRowIds ?? []), row.id]) }
            } else {
                transformNode.data = { ...transformNode.data, targetRowIds: uniqueIds([...(transformNode.data?.targetRowIds ?? []), row.id]) }
            }
            schema.value = [...schema.value, transformEdge]
            isSaved.value = false
            logAction('pipeline_transform_changed', { transform_name: transformNode.label })
            nextTick(() => {
                whisper('schema-patch', {
                    add: [transformEdge],
                    update: [{ id: transformNode.id, data: { ...transformNode.data } }],
                })
            })
            return
        }
        const isReferenceLink = !!srcTable?.data?.reference || !!tgtTable?.data?.reference
        const connColor = defaultConnectionColor?.value
        if (isReferenceLink) {
            params.style = { stroke: '#8b5cf6', strokeDasharray: '6 4' }
            params.data = { ...params.data, color: '#8b5cf6', linkKind: 'reference', exportable: false }
        } else if (connColor) {
            params.style = { stroke: connColor }
            params.data = { ...params.data, color: connColor, linkKind: 'relationship', exportable: true }
        }
        addEdges([params])
        isSaved.value = false
        logAction(isReferenceLink ? 'reference_link_created' : 'connection_created', { from_table: srcTable?.label, from_column: srcRow?.label, to_table: tgtTable?.label, to_column: tgtRow?.label })
        nextTick(() => {
            const newEdge = schema.value.find(el =>
                el.source === params.source && el.target === params.target &&
                el.sourceHandle === params.sourceHandle && el.targetHandle === params.targetHandle
            )
            if (newEdge) {
                whisper('schema-patch', { add: [newEdge] })
                if (isReferenceLink) return
                // Defer past the click event that completes the connection,
                // otherwise the modal's onClickOutside closes it immediately.
                setTimeout(() => openRelationshipModalAtEdgeCenter(newEdge), 0)
            }
        })
    }

    const openRelationshipModalAtEdgeCenter = (edge) => {
        const srcNode = findNode(edge.source)
        const tgtNode = findNode(edge.target)
        if (!srcNode || !tgtNode) return
        const center = (node) => ({
            x: node.computedPosition.x + (node.dimensions.width / 2),
            y: node.computedPosition.y + (node.dimensions.height / 2),
        })
        const src = center(srcNode)
        const tgt = center(tgtNode)
        const screen = flowToScreenCoordinate({
            x: (src.x + tgt.x) / 2,
            y: (src.y + tgt.y) / 2,
        })
        selectedEdge.value = edge
        modalPosition.value = { x: screen.x + window.scrollX, y: screen.y + window.scrollY }
        showRelationshipModal.value = true
    }

    const onEdgeUpdate = ({ edge, connection }) => {
        snapshot()
        const oldEdgeId = edge.id
        const nextConnection = { ...connection }
        const sourceElement = schema.value.find(el => el.id === nextConnection.source)
        const targetElement = schema.value.find(el => el.id === nextConnection.target)
        const isTransformConnection = edge.data?.linkKind === 'transform'
            || sourceElement?.type === 'pipeline-transform'
            || targetElement?.type === 'pipeline-transform'
        const oldTransform = schema.value.find(el => (el.id === edge.source || el.id === edge.target) && el.type === 'pipeline-transform')
        let transformRowNode = null
        if (isTransformConnection) {
            const transformNode = sourceElement?.type === 'pipeline-transform' ? sourceElement : targetElement
            const rowNode = sourceElement?.type === 'row' ? sourceElement : targetElement
            if (!transformNode || !rowNode) return
            transformRowNode = rowNode
            const isInput = isReferenceRow(rowNode)
            const transformCenterX = nodeCenterX(transformNode)
            nextConnection.source = isInput ? rowNode.id : transformNode.id
            nextConnection.target = isInput ? transformNode.id : rowNode.id
            nextConnection.sourceHandle = isInput ? rowSourceHandleFacing(rowNode, transformCenterX) : 'source-right'
            nextConnection.targetHandle = isInput ? 'target-left' : rowTargetHandleFacing(rowNode, transformCenterX)
            if (oldTransform) {
                oldTransform.data = {
                    ...oldTransform.data,
                    sourceRowIds: (oldTransform.data?.sourceRowIds ?? []).filter(id => id !== edge.source),
                    targetRowIds: (oldTransform.data?.targetRowIds ?? []).filter(id => id !== edge.target),
                }
            }
            transformNode.data = {
                ...transformNode.data,
                sourceRowIds: isInput
                    ? uniqueIds([...(transformNode.data?.sourceRowIds ?? []), rowNode.id])
                    : (transformNode.data?.sourceRowIds ?? []),
                targetRowIds: !isInput
                    ? uniqueIds([...(transformNode.data?.targetRowIds ?? []), rowNode.id])
                    : (transformNode.data?.targetRowIds ?? []),
            }
        }
        updateEdge(edge, nextConnection)
        isSaved.value = false
        nextTick(() => {
            const newEdge = schema.value.find(el =>
                el.source === nextConnection.source && el.target === nextConnection.target &&
                el.sourceHandle === nextConnection.sourceHandle && el.targetHandle === nextConnection.targetHandle
            )
            if (isTransformConnection && newEdge) {
                newEdge.type = 'transform'
                newEdge.style = { stroke: '#f59e0b', strokeDasharray: '4 4' }
                newEdge.data = { ...(newEdge.data ?? {}), linkKind: 'transform', exportable: false, direction: isReferenceRow(transformRowNode) ? 'input' : 'output' }
            }
            const updates = []
            if (oldTransform) updates.push({ id: oldTransform.id, data: { ...oldTransform.data } })
            const newTransform = schema.value.find(el => (el.id === nextConnection.source || el.id === nextConnection.target) && el.type === 'pipeline-transform')
            if (newTransform && newTransform.id !== oldTransform?.id) updates.push({ id: newTransform.id, data: { ...newTransform.data } })
            whisper('schema-patch', { remove: [oldEdgeId], add: newEdge ? [newEdge] : [], update: updates })
        })
    }

    const updateConnectionLineType = (relationshipType) => {
        snapshot()
        if (relationshipType === 'many-to-many') {
            const result = TableActions.createPivotTable(schema, selectedEdge.value, diagramDbType.value, defaultTableColor?.value)
            showRelationshipModal.value = false
            isSaved.value = false
            if (result) {
                const pivotTable = schema.value.find(el => el.id === result.pivotTableId)
                logAction('table_created', { table_name: pivotTable?.label })
                const addedNodes = schema.value.filter(el =>
                    el.id === result.pivotTableId ||
                    el.parentNode === result.pivotTableId ||
                    result.addedEdgeIds.includes(el.id)
                )
                whisper('schema-patch', { add: addedNodes, remove: [result.removedEdgeId] })
            }
            return
        }

        TableActions.updateConnectionLineType(schema, selectedEdge, relationshipType)
        showRelationshipModal.value = false
        isSaved.value = false
        const srcRow = schema.value.find(el => el.id === selectedEdge.value.source)
        const tgtRow = schema.value.find(el => el.id === selectedEdge.value.target)
        const srcTable = schema.value.find(el => el.id === srcRow?.parentNode)
        const tgtTable = schema.value.find(el => el.id === tgtRow?.parentNode)
        logAction('relationship_changed', { type: relationshipType, from_table: srcTable?.label, from_column: srcRow?.label, to_table: tgtTable?.label, to_column: tgtRow?.label })
        const edge = schema.value.find(el => el.id === selectedEdge.value.id)
        if (edge) whisper('schema-patch', { update: [{ id: edge.id, data: { ...edge.data } }] })
    }

    const onRowChange = (id) => {
        snapshot()
        isSaved.value = false
        const node = schema.value.find(el => el.id === id)
        if (node) {
            if (node.data.ontologyImportedSqlType && node.data.sqlType !== node.data.ontologyImportedSqlType) {
                node.data.ontologyBaseType = null
                node.data.ontologyImportedSqlType = null
            }
            whisper('schema-patch', {
                update: [{ id, data: { sqlType: node.data.sqlType, valueTypeId: node.data.valueTypeId ?? null, keyMod: node.data.keyMod, nullable: node.data.nullable, indexed: node.data.indexed ?? true, unsigned: node.data.unsigned, defaultValue: node.data.defaultValue, description: node.data.description ?? node.data.comment ?? '', ontologyBaseType: node.data.ontologyBaseType ?? null, ontologyImportedSqlType: node.data.ontologyImportedSqlType ?? null } }]
            })
        }
    }

    const updateNote = (id, note) => {
        snapshot(`note-${id}`)
        const node = schema.value.find(el => el.id === id)
        if (!node) return
        node.data = { ...node.data, description: note }
        isSaved.value = false
        whisper('schema-patch', { update: [{ id, data: { description: note } }] })
    }

    const updateTableActions = (id, actions) => {
        snapshot(`table-actions-${id}`)
        const node = schema.value.find(el => el.id === id && el.type === 'table')
        if (!node) return
        const ontologyActions = {
            create: !!actions.create,
            modify: !!actions.modify,
            delete: !!actions.delete,
        }
        const titlePropertyRowId = typeof actions.titlePropertyRowId === 'string'
            ? actions.titlePropertyRowId
            : null
        const implementsInterfaces = Array.isArray(actions.implementsInterfaces)
            ? actions.implementsInterfaces
            : []
        node.data = { ...node.data, ontologyActions, titlePropertyRowId, implementsInterfaces }
        isSaved.value = false
        whisper('schema-patch', { update: [{ id, data: { ontologyActions, titlePropertyRowId, implementsInterfaces } }] })
    }

    const updateLabel = (id, newLabel) => {
        snapshot()
        const element = schema.value.find(el => el.id === id)
        if (element) {
            element.label = newLabel.replace(' ', '_')
            isSaved.value = false
            whisper('schema-patch', { update: [{ id, label: element.label }] })
        }
    }

    const updateTransformLabel = (id, newLabel) => {
        snapshot()
        const element = schema.value.find(el => el.id === id && el.type === 'pipeline-transform')
        if (!element) return
        element.label = String(newLabel || '').trim() || 'Pipeline Transform'
        isSaved.value = false
        logAction('pipeline_transform_changed', { transform_name: element.label })
        whisper('schema-patch', { update: [{ id, label: element.label }] })
    }

    const updateEdgeColor = (color) => {
        snapshot(`edge-color-${selectedEdge.value?.id}`)
        const edge = schema.value.find(el => el.id === selectedEdge.value?.id)
        if (!edge) return
        edge.style = { ...edge.style, stroke: color }
        edge.data = { ...edge.data, color }
        isSaved.value = false
        whisper('schema-patch', { update: [{ id: edge.id, style: { ...edge.style }, data: { color } }] })
    }

    const updateTableColor = (tableId, color) => {
        snapshot(`table-color-${tableId}`)
        const tableNode = schema.value.find(el => el.id === tableId)
        if (!tableNode) return
        tableNode.style = tableNode.data?.reference
            ? { ...tableNode.style, background: colorWithAlpha(color, 0.25), borderColor: '#8b5cf6', border: '1px dashed #8b5cf6' }
            : { ...tableNode.style, background: color, borderColor: color }
        tableNode.data = { ...tableNode.data, color }

        const childIds = schema.value.filter(el => el.parentNode === tableId).map(el => el.id)
        const connectedEdges = schema.value.filter(el => el.target && childIds.includes(el.target))
        connectedEdges.forEach(edge => {
            edge.style = { ...edge.style, stroke: color }
            edge.data = { ...edge.data, color }
        })

        isSaved.value = false
        const edgeUpdates = connectedEdges.map(edge => ({ id: edge.id, style: { ...edge.style }, data: { color } }))
        whisper('schema-patch', { update: [{ id: tableId, style: { ...tableNode.style }, data: { color } }, ...edgeUpdates] })
    }

    // --- Options modal ---

    const closeAllOptionsModals = () => {
        schema.value.filter(el => el.type === 'row' && el.data.showOptionsModal).forEach(row => {
            row.data.showOptionsModal = false
        })
    }

    const toggleOptionsModal = (id) => {
        const row = schema.value.find(el => el.id === id)
        const willOpen = !row.data.showOptionsModal
        closeAllOptionsModals()
        if (willOpen) {
            row.data.modalPosition = { x: 350, y: 0 }
            row.data.showOptionsModal = true
        }
    }

    // --- Table constraints ---

    const onTableConstraintsChange = (tableId, newConstraints) => {
        snapshot()
        const tableNode = schema.value?.find(el => el.id === tableId)
        if (!tableNode) return
        tableNode.data.uniqueTogether = newConstraints
        isSaved.value = false
        whisper('schema-patch', { update: [{ id: tableId, data: { uniqueTogether: newConstraints } }] })
    }

    const onTableFulltextChange = (tableId, newIndexes) => {
        snapshot()
        const tableNode = schema.value?.find(el => el.id === tableId)
        if (!tableNode) return
        tableNode.data.fulltextIndexes = newIndexes
        isSaved.value = false
        whisper('schema-patch', { update: [{ id: tableId, data: { fulltextIndexes: newIndexes } }] })
    }

    // --- Relationship modal ---

    const openRelationshipModal = ({ edge, event }) => {
        if (edge.data?.exportable === false && !['reference', 'transform'].includes(edge.data?.linkKind)) return
        selectedEdge.value = edge
        modalPosition.value = { x: event.clientX + window.scrollX, y: event.clientY + window.scrollY }
        showRelationshipModal.value = true
    }

    const closeRelationshipModal = () => {
        showRelationshipModal.value = false
    }

    const toggleRowSelection = (rowId, event = null) => {
        const row = schema.value.find(el => el.id === rowId && el.type === 'row')
        if (!row) return
        if (!event?.metaKey && !event?.ctrlKey && !event?.shiftKey) return
        event.preventDefault()
        event.stopPropagation()
        if (selectedRowIds.value.includes(rowId)) {
            selectedRowIds.value = selectedRowIds.value.filter(id => id !== rowId)
        } else {
            selectedRowIds.value = [...selectedRowIds.value, rowId]
        }
    }

    const clearRowSelection = () => {
        selectedRowIds.value = []
    }

    const buildTransformEdges = (transformNode, referenceRows, targetRows) => {
        const transformCenterX = nodeCenterX(transformNode)
        return [
            ...referenceRows.map(row => ({
                id: `transform-edge-${Math.random().toString(36).slice(2)}`,
                type: 'transform',
                source: row.id,
                target: transformNode.id,
                sourceHandle: rowSourceHandleFacing(row, transformCenterX),
                targetHandle: 'target-left',
                updatable: true,
                data: { linkKind: 'transform', exportable: false, direction: 'input' },
                style: { stroke: '#f59e0b', strokeDasharray: '4 4' },
            })),
            ...targetRows.map(row => ({
                id: `transform-edge-${Math.random().toString(36).slice(2)}`,
                type: 'transform',
                source: transformNode.id,
                target: row.id,
                sourceHandle: 'source-right',
                targetHandle: rowTargetHandleFacing(row, transformCenterX),
                updatable: true,
                data: { linkKind: 'transform', exportable: false, direction: 'output' },
                style: { stroke: '#f59e0b', strokeDasharray: '4 4' },
            })),
        ]
    }

    const attachSelectedRowsToTransform = (transformId) => {
        const transformNode = schema.value.find(el => el.id === transformId && el.type === 'pipeline-transform')
        if (!transformNode) throw new Error('Pipeline transform not found.')
        const selectedRows = selectedRowIds.value
            .map(id => schema.value.find(el => el.id === id && el.type === 'row'))
            .filter(Boolean)
        if (!selectedRows.length) throw new Error('Select rows to attach first.')

        const existingPairs = new Set(schema.value
            .filter(edge => edge.data?.linkKind === 'transform' && (edge.source === transformId || edge.target === transformId))
            .map(edge => `${edge.source}->${edge.target}`))
        const referenceRows = selectedRows
            .filter(row => isReferenceRow(row))
            .filter(row => !existingPairs.has(`${row.id}->${transformId}`))
        const targetRows = selectedRows
            .filter(row => !isReferenceRow(row))
            .filter(row => !existingPairs.has(`${transformId}->${row.id}`))
        const edges = buildTransformEdges(transformNode, referenceRows, targetRows)
        if (!edges.length) throw new Error('Selected rows are already attached.')

        snapshot()
        transformNode.data = {
            ...transformNode.data,
            sourceRowIds: uniqueIds([...(transformNode.data?.sourceRowIds ?? []), ...referenceRows.map(row => row.id)]),
            targetRowIds: uniqueIds([...(transformNode.data?.targetRowIds ?? []), ...targetRows.map(row => row.id)]),
        }
        schema.value = [...schema.value, ...edges]
        selectedRowIds.value = []
        isSaved.value = false
        logAction('pipeline_transform_changed', { transform_name: transformNode.label, inputs: referenceRows.length, outputs: targetRows.length })
        whisper('schema-patch', { add: edges, update: [{ id: transformNode.id, data: { ...transformNode.data } }] })
        return edges.length
    }

    const createEmptyTransform = () => {
        snapshot()
        const transformNode = {
            id: `transform-${Math.random().toString(36).slice(2)}`,
            type: 'pipeline-transform',
            label: 'Pipeline Transform',
            position: { x: 240, y: 240 },
            zIndex: 1000,
            data: {
                exportable: false,
                transformKind: 'reference-to-object',
                sourceRowIds: [],
                targetRowIds: [],
            },
        }
        schema.value = [...schema.value, transformNode]
        selectedRowIds.value = []
        isSaved.value = false
        logAction('pipeline_transform_created', { inputs: 0, outputs: 0 })
        whisper('schema-patch', { add: [transformNode] })
        return transformNode.id
    }

    const createTransformFromSelection = () => {
        const selectedRows = selectedRowIds.value
            .map(id => schema.value.find(el => el.id === id && el.type === 'row'))
            .filter(Boolean)
        const referenceRows = selectedRows.filter(row => schema.value.find(el => el.id === row.parentNode)?.data?.reference)
        const targetRows = selectedRows.filter(row => !schema.value.find(el => el.id === row.parentNode)?.data?.reference)
        if (selectedRows.length > 0 && (!referenceRows.length || !targetRows.length)) {
            throw new Error('Select at least one reference row and one regular row.')
        }

        snapshot()
        const rowNodes = selectedRows.map(row => findNode(row.id)).filter(Boolean)
        const average = rowNodes.reduce((acc, node) => ({ x: acc.x + node.computedPosition.x, y: acc.y + node.computedPosition.y }), { x: 0, y: 0 })
        const position = rowNodes.length
            ? { x: average.x / rowNodes.length + 140, y: average.y / rowNodes.length }
            : { x: 240, y: 240 }
        const transformId = `transform-${Math.random().toString(36).slice(2)}`
        const transformNode = {
            id: transformId,
            type: 'pipeline-transform',
            label: 'Pipeline Transform',
            position,
            zIndex: 1000,
            data: {
                exportable: false,
                transformKind: 'reference-to-object',
                sourceRowIds: referenceRows.map(row => row.id),
                targetRowIds: targetRows.map(row => row.id),
            },
        }
        const edges = buildTransformEdges(transformNode, referenceRows, targetRows)

        schema.value = [...schema.value, transformNode, ...edges]
        selectedRowIds.value = []
        isSaved.value = false
        logAction('pipeline_transform_created', { inputs: referenceRows.length, outputs: targetRows.length })
        whisper('schema-patch', { add: [transformNode, ...edges] })
    }

    return {
        isPlacingTable, isConnecting, copyingTableId, selectedRowIds,
        selectedEdge, showRelationshipModal, modalPosition,
        addTable, addReferenceTable, importReferenceJsonSchemas, copyTable, onPaneClick,
        addRow, addRowAfter, deleteEdge, deleteNode, onConnect, onEdgeUpdate,
        updateConnectionLineType, onRowChange, updateLabel, updateTransformLabel, updateEdgeColor, updateTableColor, updateNote, updateTableActions,
        onTableConstraintsChange, onTableFulltextChange, toggleOptionsModal,
        toggleRowSelection, clearRowSelection, createTransformFromSelection, createEmptyTransform, attachSelectedRowsToTransform,
        openRelationshipModal, closeRelationshipModal,
    }
}
