import axios from '@/axios'

// Foundry AIP diagram agent client. Sessions are shared across diagram
// collaborators; all calls go through the versioned API and the user's own
// Foundry token stays server-side.
const base = (diagramId) => `/api/v1/diagrams/${diagramId}/foundry/agent`

export const DiagramAgent = {
    async models(diagramId) {
        const { data } = await axios.get(`/api/v1/diagrams/${diagramId}/foundry/llm/models`)
        return data
    },

    async listSessions(diagramId, includeArchived = false) {
        const { data } = await axios.get(`${base(diagramId)}/sessions`, {
            params: includeArchived ? { include_archived: 1 } : {},
        })
        return data.data ?? []
    },

    async createSession(diagramId, payload = {}) {
        const { data } = await axios.post(`${base(diagramId)}/sessions`, payload)
        return data.data
    },

    async getSession(diagramId, sessionId) {
        const { data } = await axios.get(`${base(diagramId)}/sessions/${sessionId}`)
        return data.data
    },

    async renameSession(diagramId, sessionId, title) {
        const { data } = await axios.patch(`${base(diagramId)}/sessions/${sessionId}`, { title })
        return data.data
    },

    async archiveSession(diagramId, sessionId) {
        const { data } = await axios.post(`${base(diagramId)}/sessions/${sessionId}/archive`)
        return data.data
    },

    async unarchiveSession(diagramId, sessionId) {
        const { data } = await axios.post(`${base(diagramId)}/sessions/${sessionId}/unarchive`)
        return data.data
    },

    async sendMessage(diagramId, sessionId, message, { model = null, allowDestructive = false } = {}) {
        const payload = { message }
        if (model) payload.model = model
        if (allowDestructive) payload.allow_destructive = true
        const { data } = await axios.post(`${base(diagramId)}/sessions/${sessionId}/messages`, payload)
        // A 422 archived-session response is returned as { error: {...} }.
        if (data?.error) {
            const err = new Error(data.error.message || 'Agent request failed.')
            err.foundryCode = data.error.code
            throw err
        }
        return data.data
    },

    async markApplied(diagramId, sessionId, messageId) {
        const { data } = await axios.post(`${base(diagramId)}/sessions/${sessionId}/messages/${messageId}/applied`)
        return data.data
    },

    async unmarkApplied(diagramId, sessionId, messageId) {
        const { data } = await axios.delete(`${base(diagramId)}/sessions/${sessionId}/messages/${messageId}/applied`)
        return data.data
    },
}
