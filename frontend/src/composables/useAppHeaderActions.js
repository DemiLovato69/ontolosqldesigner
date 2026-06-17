import { reactive } from 'vue'

export const appHeaderActions = reactive({
    diagram: null,
})

export function setDiagramHeaderActions(actions) {
    appHeaderActions.diagram = actions
}

export function clearDiagramHeaderActions(actions) {
    if (!actions || appHeaderActions.diagram === actions) {
        appHeaderActions.diagram = null
    }
}
