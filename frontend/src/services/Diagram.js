import axios from '@/axios'
import { useToast } from 'vue-toast-notification'

const $toast = useToast()

async function request(fn) {
    try {
        return await fn()
    } catch (error) {
        if (error.response?.status === 413) {
            $toast.error('Upload chunk is too large for the server. Please try again or contact support.')
        } else {
            $toast.error(error.response?.data.message ?? 'Something went wrong!')
        }
    }
}

const transientElementKeys = new Set([
    'computedPosition',
    'dimensions',
    'dragging',
    'events',
    'handleBounds',
    'initialized',
    'isParent',
    'resizing',
    'selected',
])

const transientDataKeys = new Set([
    'editing',
    'modalPosition',
    'showModal',
    'showOptionsModal',
])

function schemaForSave(schema) {
    return schema.map((element) => {
        const clean = {}
        for (const [key, value] of Object.entries(element)) {
            if (!transientElementKeys.has(key)) clean[key] = value
        }
        if (clean.data && typeof clean.data === 'object') {
            clean.data = Object.fromEntries(
                Object.entries(clean.data).filter(([key]) => !transientDataKeys.has(key))
            )
        }
        return clean
    })
}

export const Diagram = {
    get: (id) =>
        request(async () => {
            const response = await axios.get(`/api/diagrams/${id}`)
            return response.data.data
        }),

    getByToken: async (token) => {
        try {
            const response = await axios.get(`/api/diagrams/shared/${token}`)
            return response.data.data
        } catch (error) {
            if (error.response?.status === 403 && error.response?.data?.pending_approval) {
                return { pending_approval: true }
            }
            $toast.error(error.response?.data?.message ?? 'Something went wrong!')
            return null
        }
    },

    import: (id, format, script) =>
        request(async () => {
            const response = await axios.post(`/api/diagrams/import/${format}/${id}`, script, {
                headers: { 'Content-Type': 'text/plain' },
            })
            return response.data
        }),

    importFile: (id, format, file, onProgress = null) =>
        request(async () => {
            const chunkSize = 4 * 1024 * 1024
            const chunksTotal = Math.ceil(file.size / chunkSize)
            const upload = await axios.post(`/api/diagrams/${id}/imports`, {
                format,
                size: file.size,
                chunk_size: chunkSize,
                chunks_total: chunksTotal,
                original_name: file.name,
            })

            for (let index = 0; index < chunksTotal; index++) {
                const start = index * chunkSize
                const end = Math.min(start + chunkSize, file.size)
                await axios.put(
                    `/api/diagrams/${id}/imports/${upload.data.id}/chunks/${index}`,
                    file.slice(start, end),
                    { headers: { 'Content-Type': 'application/octet-stream' } }
                )
                if (onProgress) onProgress(Math.round(((index + 1) / chunksTotal) * 100), 'Uploading file')
            }

            if (onProgress) onProgress(100, 'Finalizing upload')
            const response = await axios.post(`/api/diagrams/${id}/imports/${upload.data.id}/complete`)
            if (onProgress) onProgress(100, 'Processing import')
            return response.data
        }),

    importStatus: (id) =>
        request(async () => {
            const response = await axios.get(`/api/diagrams/sql/import-status/${id}`)
            return response.data
        }),

    export: (id) =>
        request(async () => {
            const response = await axios.post(`/api/diagrams/sql/export/${id}`)
            return response.data
        }),

    exportStatus: (id) =>
        request(async () => {
            const response = await axios.get(`/api/diagrams/sql/export-status/${id}`)
            return response.data
        }),

    exportJson: (id) =>
        request(async () => {
            const response = await axios.get(`/api/diagrams/json/export/${id}`)
            return JSON.stringify(response.data, null, 2)
        }),

    exportOntology: async (id) => {
        const response = await axios.get(`/api/diagrams/ontology/export/${id}`, { responseType: 'blob' })
        return response.data
    },

    save: (id, schema, valueTypes = []) =>
        request(async () => {
            const response = await axios.put(`/api/diagrams/${id}`, { id, schema: schemaForSave(schema), value_types: valueTypes })
            $toast.success(response.data.message)
            return true
        }),

    share: (id) =>
        request(async () => {
            const response = await axios.post(`/api/diagrams/${id}/share`)
            return response.data.share_token
        }),

    unshare: (id) =>
        request(async () => {
            await axios.delete(`/api/diagrams/${id}/share`)
        }),

    saveByToken: (token, schema, valueTypes = []) =>
        request(async () => {
            await axios.patch(`/api/diagrams/shared/${token}`, { schema: schemaForSave(schema), value_types: valueTypes })
            return true
        }),

    updateShareAccess: (id, payload) =>
        request(async () => {
            const response = await axios.patch(`/api/diagrams/${id}/share`, payload)
            return response.data
        }),

    updateRequireApproval: (id, requireApproval) =>
        request(async () => {
            const response = await axios.patch(`/api/diagrams/${id}/share`, { require_approval: requireApproval })
            return response.data.require_approval
        }),

    setAccessMode: (id, mode) =>
        request(async () => {
            const response = await axios.patch(`/api/diagrams/${id}/share`, { access: mode })
            return response.data
        }),

    getVisitors: (id) =>
        request(async () => {
            const response = await axios.get(`/api/diagrams/${id}/visitors`)
            return response.data
        }),

    getInvites: (id) =>
        request(async () => {
            const response = await axios.get(`/api/diagrams/${id}/invites`)
            return response.data
        }),

    updateInvites: (id, invites) =>
        request(async () => {
            const response = await axios.put(`/api/diagrams/${id}/invites`, { invites })
            return response.data
        }),

    searchShareUsers: (query) =>
        request(async () => {
            const response = await axios.get('/api/diagrams/share-users/search', { params: { q: query } })
            return response.data
        }),

    approveVisitor: (diagramId, visitorId) =>
        request(async () => {
            const response = await axios.post(`/api/diagrams/${diagramId}/visitors/${visitorId}/approve`)
            return response.data
        }),

    updateVisitorAccess: (diagramId, visitorId, access) =>
        request(async () => {
            const response = await axios.patch(`/api/diagrams/${diagramId}/visitors/${visitorId}`, { access })
            return response.data
        }),

    duplicateByToken: (token) =>
        request(async () => {
            const response = await axios.post(`/api/diagrams/shared/${token}/duplicate`)
            return response.data.diagram
        }),

    getChangelog: (id) =>
        request(async () => {
            const response = await axios.get(`/api/diagrams/${id}/changelog`)
            return response.data.data
        }),

    addChangelogEntry: (id, action, details = null) =>
        request(async () => {
            await axios.post(`/api/diagrams/${id}/changelog`, { action, details })
        }),

}
