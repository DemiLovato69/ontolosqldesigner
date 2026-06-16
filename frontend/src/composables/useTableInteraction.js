import { ref } from 'vue'

export function useTableInteraction({ schema, whisper, isSaved, broadcastCursor, snapshot }) {
    const lastInteractedTableId = ref(null)

    const onNodeMouseEnter = ({ node }) => {
        lastInteractedTableId.value = node.type === 'table' ? node.id : node.parentNode
    }

    const onNodeMouseLeave = () => {}

    const elevateTable = (node) => {
        const tableId = node.type === 'table' ? node.id : node.parentNode
        if (!tableId) return
        lastInteractedTableId.value = tableId
        const maxZ = schema.value.reduce((m, el) => (el.zIndex > m ? el.zIndex : m), 0)
        const newZ = maxZ + 1
        schema.value.forEach(el => {
            if (el.id === tableId || el.parentNode === tableId) el.zIndex = newZ
        })
    }

    const onNodeDragStart = ({ node }) => {
        snapshot()
        elevateTable(node)
    }

    let lastNodeDragWhisper = 0
    const onNodeDrag = ({ node, event }) => {
        const now = Date.now()
        if (now - lastNodeDragWhisper < 50) return
        lastNodeDragWhisper = now
        whisper('schema-patch', { update: [{ id: node.id, position: node.position }] })
        broadcastCursor(event)
    }

    const onNodeDragStop = ({ node }) => {
        isSaved.value = false
        whisper('schema-patch', { update: [{ id: node.id, position: node.position }] })
    }

    return { onNodeMouseEnter, onNodeMouseLeave, elevateTable, onNodeDragStart, onNodeDrag, onNodeDragStop, lastInteractedTableId }
}
