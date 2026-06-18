import { reactive, watch } from 'vue'
import { useThrottleFn } from '@vueuse/core'
import { createEcho } from '@/echo.js'
import { Diagram } from '@/services/Diagram.js'
import { repairAndNormalizeSchema } from '@/services/SchemaRepair.js'

export const CURSOR_COLORS = ['#E53935', '#D81B60', '#8E24AA', '#3949AB', '#1E88E5', '#00ACC1', '#43A047', '#FB8C00']

/**
 * Manages the Laravel Echo presence channel for a diagram:
 * remote cursors, schema-patch sync, and diagram-saved notifications.
 *
 * @param {Object} opts
 * @param {string}      opts.token
 * @param {import('vue').Ref} opts.ownerIdentity  - { id, name, color }
 * @param {import('vue').Ref} opts.viewport        - VueFlow viewport
 * @param {import('vue').Ref} opts.schema          - diagram schema array
 * @param {import('vue').Ref} opts.canvasWrapperRef
 * @param {Function} opts.onDiagramSaved           - called when a remote user saves
 */
export function useDiagramPresence({ token, ownerIdentity, viewport, schema, valueTypes, ontologyMetadata = {}, canvasWrapperRef, canEdit, onDiagramSaved, onVisitorRequested, onAccessChanged }) {
    const FULL_SCHEMA_SYNC_LIMIT = 2000
    const WRITER_EVENTS = new Set(['schema-sync', 'value-types-sync', 'schema-patch'])
    const remoteCursors = reactive({})
    let echo = null
    let presenceChannel = null
    let writerChannel = null

    const whisper = (event, data) => {
        if (event === 'schema-sync' && data?.schema?.length > FULL_SCHEMA_SYNC_LIMIT) return
        const channel = WRITER_EVENTS.has(event) ? writerChannel : presenceChannel
        channel?.whisper(event, data)
    }

    const broadcastCursor = useThrottleFn((event) => {
        if (!presenceChannel || !ownerIdentity.value) return
        const rect = canvasWrapperRef.value?.getBoundingClientRect()
        if (!rect) return
        const vp = viewport.value
        const x = (event.clientX - rect.left - vp.x) / vp.zoom
        const y = (event.clientY - rect.top - vp.y) / vp.zoom
        presenceChannel.whisper('cursor-moved', { id: ownerIdentity.value.id, x, y })
    }, 40)

    const onCanvasMouseMove = (event) => broadcastCursor(event)

    const joinWriterChannel = () => {
        if (!echo || writerChannel || !canEdit?.value) return
        writerChannel = echo.join(`diagram.${token}.writers`)
            .joining((user) => {
                if (user.id !== ownerIdentity.value?.id) {
                    setTimeout(() => {
                        whisper('schema-sync', { schema: schema.value, valueTypes: valueTypes?.value ?? [], metadata: metadataSnapshot(), forUserId: user.id })
                    }, Math.random() * 200)
                }
            })
            .listenForWhisper('schema-sync', ({ schema: incoming, valueTypes: incomingValueTypes, metadata: incomingMetadata, forUserId }) => {
                if (forUserId && forUserId !== ownerIdentity.value?.id) return
                if (incoming?.length) schema.value = repairAndNormalizeSchema(incoming).schema
                if (valueTypes && Array.isArray(incomingValueTypes)) valueTypes.value = incomingValueTypes
                applyMetadata(incomingMetadata)
            })
            .listenForWhisper('value-types-sync', ({ valueTypes: incomingValueTypes }) => {
                if (valueTypes && Array.isArray(incomingValueTypes)) valueTypes.value = incomingValueTypes
            })
            .listenForWhisper('schema-patch', ({ add, remove, update }) => {
                if (remove?.length) {
                    schema.value = schema.value.filter(el => !remove.includes(el.id))
                }
                if (add?.length) {
                    schema.value = [...schema.value, ...add]
                }
                if (update?.length) {
                    for (const change of update) {
                        const el = schema.value.find(el => el.id === change.id)
                        if (!el) continue
                        const { data, ...rest } = change
                        Object.assign(el, rest)
                        if (data) {
                            const { showOptionsModal: _, modalPosition: __, ...safeData } = data
                            Object.assign(el.data, safeData)
                        }
                    }
                }
                schema.value = repairAndNormalizeSchema(schema.value).schema
            })
    }

    const leaveWriterChannel = () => {
        if (!echo || !writerChannel) return
        echo.leave(`diagram.${token}.writers`)
        writerChannel = null
    }

    const initEcho = () => {
        if (!ownerIdentity.value) return
        echo = createEcho()
        if (!echo) return

        presenceChannel = echo.join(`diagram.${token}`)
            .here((users) => {
                for (const u of users) {
                    if (u.id !== ownerIdentity.value.id) {
                        remoteCursors[u.id] = { ...u, screenX: -999, screenY: -999 }
                    }
                }
            })
            .joining((user) => {
                if (user.id !== ownerIdentity.value.id) {
                    remoteCursors[user.id] = { ...user, screenX: -999, screenY: -999 }
                }
            })
            .leaving((user) => {
                delete remoteCursors[user.id]
            })
            .listenForWhisper('cursor-moved', ({ id, x, y }) => {
                if (!id || id === ownerIdentity.value?.id || !remoteCursors[id]) return
                remoteCursors[id].flowX = x
                remoteCursors[id].flowY = y
                remoteCursors[id].screenX = x * viewport.value.zoom + viewport.value.x
                remoteCursors[id].screenY = y * viewport.value.zoom + viewport.value.y
            })
            .listenForWhisper('diagram-saved', () => {
                if (onDiagramSaved) onDiagramSaved()
            })
            .listen('.schema.imported', async ({ imported_by }) => {
                if (String(imported_by) === ownerIdentity.value?.id) return
                const result = await Diagram.getByToken(token)
                if (result?.schema) schema.value = repairAndNormalizeSchema(result.schema).schema
                if (valueTypes && Array.isArray(result?.value_types)) valueTypes.value = result.value_types
                applyMetadata(metadataFromDiagram(result))
            })
            .listen('.visitor.requested', () => {
                if (onVisitorRequested) onVisitorRequested()
            })
            .listen('.visitor.access.changed', ({ user_id, access }) => {
                if (String(user_id) === ownerIdentity.value?.id && onAccessChanged) onAccessChanged(access)
            })
        joinWriterChannel()
    }

    const cleanupEcho = () => {
        if (echo) {
            leaveWriterChannel()
            echo.leave(`diagram.${token}`)
            echo.disconnect()
        }
        echo = null
        presenceChannel = null
        Object.keys(remoteCursors).forEach(k => delete remoteCursors[k])
    }

    watch(viewport, (vp) => {
        for (const id in remoteCursors) {
            const c = remoteCursors[id]
            if (c.flowX !== undefined) {
                c.screenX = c.flowX * vp.zoom + vp.x
                c.screenY = c.flowY * vp.zoom + vp.y
            }
        }
    }, { deep: true })

    watch(canEdit, (editable) => {
        if (!echo) return
        editable ? joinWriterChannel() : leaveWriterChannel()
    })

    const metadataSnapshot = () => Object.fromEntries(
        Object.entries(ontologyMetadata).map(([key, refValue]) => [key, refValue.value ?? []])
    )

    const applyMetadata = (metadata) => {
        if (!metadata || typeof metadata !== 'object') return
        for (const [key, refValue] of Object.entries(ontologyMetadata)) {
            if (Array.isArray(metadata[key])) refValue.value = metadata[key]
        }
    }

    const metadataFromDiagram = (diagram) => ({
        interfaces: diagram?.interfaces ?? [],
        interfaceLinkConstraints: diagram?.interface_link_constraints ?? [],
        customActions: diagram?.custom_actions ?? [],
        sharedPropertyTypes: diagram?.shared_property_types ?? [],
    })

    return { remoteCursors, whisper, initEcho, cleanupEcho, onCanvasMouseMove, broadcastCursor }
}
