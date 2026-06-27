import axios from '@/axios'

// Read-only Foundry Platform integration (ontology diagrams only). All calls go
// through the versioned API; Foundry tokens stay server-side.
const base = (diagramId) => `/api/v1/diagrams/${diagramId}/foundry`

export const Foundry = {
    async hosts() {
        const { data } = await axios.get('/api/v1/foundry/hosts')
        return data
    },

    async connections() {
        const { data } = await axios.get('/api/v1/foundry/connections')
        return data.data ?? []
    },

    async disconnect(connectionId) {
        await axios.delete(`/api/v1/foundry/connections/${connectionId}`)
    },

    async getConfig(diagramId) {
        const { data } = await axios.get(`${base(diagramId)}/config`)
        return data.data
    },

    async updateConfig(diagramId, payload) {
        const { data } = await axios.put(`${base(diagramId)}/config`, payload)
        return data.data
    },

    async status(diagramId) {
        const { data } = await axios.get(`${base(diagramId)}/connection-status`)
        return data.data
    },

    async authorize(diagramId, redirectUri = null) {
        const { data } = await axios.post(`${base(diagramId)}/oauth/authorize`, redirectUri ? { redirect_uri: redirectUri } : {})
        return data.data
    },

    async connectWithToken(diagramId, token, expiresAt = null) {
        const payload = { token }
        if (expiresAt) payload.expires_at = expiresAt
        const { data } = await axios.post(`${base(diagramId)}/token`, payload)
        return data.data
    },

    async spaces(diagramId, params = {}) {
        const { data } = await axios.get(`${base(diagramId)}/spaces`, { params })
        return data
    },

    async folderChildren(diagramId, rid, params = {}) {
        const { data } = await axios.get(`${base(diagramId)}/folders`, { params: { rid, ...params } })
        return data
    },

    async ontologies(diagramId) {
        const { data } = await axios.get(`${base(diagramId)}/ontologies`)
        return data
    },

    async listDatasets(diagramId, params = {}) {
        const { data } = await axios.get(`${base(diagramId)}/datasets`, { params })
        return data
    },

    async getDataset(diagramId, datasetRid) {
        const { data } = await axios.get(`${base(diagramId)}/datasets/${encodeURIComponent(datasetRid)}`)
        return data
    },

    async getDatasetSchema(diagramId, datasetRid, params = {}) {
        const { data } = await axios.get(`${base(diagramId)}/datasets/${encodeURIComponent(datasetRid)}/schema`, { params })
        return data
    },

    async listFiles(diagramId, datasetRid, params = {}) {
        const { data } = await axios.get(`${base(diagramId)}/datasets/${encodeURIComponent(datasetRid)}/files`, { params })
        return data
    },
}

export function foundryErrorMessage(error, fallback = 'Foundry request failed.') {
    return error?.response?.data?.error?.message
        ?? error?.response?.data?.message
        ?? fallback
}
