const MAX_HISTORY = 50

export function useUndoHistory(schema, valueTypes = null) {
    const history = []
    const redoStack = []
    let lastSessionKey = null

    const snapshot = (sessionKey = null) => {
        if (!schema.value) return
        if (sessionKey && sessionKey === lastSessionKey) return
        history.push(snapshotState())
        if (history.length > MAX_HISTORY) history.shift()
        redoStack.length = 0
        lastSessionKey = sessionKey
    }

    const undo = () => {
        if (!history.length) return null
        redoStack.push(snapshotState())
        lastSessionKey = null
        return history.pop()
    }

    const redo = () => {
        if (!redoStack.length) return null
        history.push(snapshotState())
        lastSessionKey = null
        return redoStack.pop()
    }

    const snapshotState = () => {
        const state = { schema: schema.value }
        if (valueTypes) state.valueTypes = valueTypes.value
        return JSON.parse(JSON.stringify(state))
    }

    return { snapshot, undo, redo }
}
