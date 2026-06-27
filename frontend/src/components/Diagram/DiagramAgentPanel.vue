<template>
    <aside class="agent" aria-label="Diagram agent">
        <header class="agent__head">
            <div class="agent__title">
                <SvgIcon name="sparkles" :size="16" />
                <span>Diagram Agent</span>
            </div>
            <button class="agent__icon-btn" type="button" title="Close" @click="$emit('close')">
                <SvgIcon name="close" :size="16" />
            </button>
        </header>

        <p v-if="!isOntology" class="agent__notice">The agent is available for ontology diagrams only.</p>
        <p v-else-if="!diagramId" class="agent__notice">Save the diagram to use the agent.</p>
        <p v-else-if="loadingModels" class="agent__notice">Loading…</p>
        <p v-else-if="!enabled" class="agent__notice">The diagram agent is not enabled on this server.</p>
        <p v-else-if="!models.length" class="agent__notice">No agent models are configured. Ask an administrator to add one in <code>/admin/foundry</code>.</p>

        <template v-else>
            <div class="agent__bar">
                <select v-model="selectedModel" class="agent__select" :disabled="sending" title="Model">
                    <option v-for="m in models" :key="m.id" :value="m.model">{{ m.display_name }}</option>
                </select>
                <select class="agent__select agent__select--session" :value="activeSessionId ?? ''" @change="onSelectSession($event.target.value)">
                    <option value="">{{ sessions.length ? 'Select session…' : 'No sessions yet' }}</option>
                    <option v-for="s in sessions" :key="s.id" :value="s.id">
                        {{ s.title || 'Untitled session' }}{{ s.archived ? ' (archived)' : '' }}
                    </option>
                </select>
                <button class="agent__icon-btn" type="button" title="New session" :disabled="!canEdit || sending" @click="newSession">
                    <SvgIcon name="edit" :size="15" />
                </button>
            </div>

            <label class="agent__archived-toggle">
                <input type="checkbox" v-model="includeArchived" @change="loadSessions" />
                Show archived
            </label>

            <div ref="scrollRef" class="agent__messages">
                <p v-if="activeSession && !messages.length" class="agent__hint">
                    Ask the agent to design or refine this diagram. It proposes changes you review before applying.
                </p>
                <p v-else-if="!activeSession" class="agent__hint">
                    Start a session and describe what you want, e.g. “Add customer, order, and order_line tables with relationships.”
                </p>

                <div v-for="message in messages" :key="message.id" :class="['agent__msg', `agent__msg--${message.role}`]">
                    <div class="agent__msg-meta">
                        <span>{{ message.role === 'user' ? (message.user?.name || 'You') : 'Agent' }}</span>
                        <span v-if="message.model" class="agent__msg-model">{{ message.model }}</span>
                    </div>

                    <div v-if="message.role === 'user'" class="agent__bubble">{{ message.prompt }}</div>

                    <template v-else>
                        <div v-if="message.status === 'failed'" class="agent__bubble agent__bubble--error">
                            {{ message.error_message || 'The agent could not complete this request.' }}
                        </div>
                        <div v-else class="agent__bubble">{{ message.message || 'No changes proposed.' }}</div>

                        <div v-if="patchOps(message).length" class="agent__patch">
                            <div class="agent__patch-head">
                                Proposed changes
                                <span>{{ patchOps(message).length }}</span>
                            </div>
                            <ul class="agent__patch-list">
                                <li v-for="(op, i) in patchOps(message)" :key="i" :class="{ 'is-destructive': isDestructive(op) }">
                                    {{ operationLabel(op) }}
                                </li>
                            </ul>
                            <div v-if="hasDestructive(message)" class="agent__destructive">
                                <label><input type="checkbox" v-model="allowDestructive" /> Allow delete/rename operations</label>
                            </div>
                            <div v-if="appliedIds.has(message.id)" class="agent__applied">
                                <span>Applied to the diagram.</span>
                                <button
                                    v-if="canEdit && undoableId === message.id"
                                    class="agent__link"
                                    type="button"
                                    :disabled="applying"
                                    @click="undoApply(message)"
                                >Undo</button>
                            </div>
                            <div v-else-if="canEdit" class="agent__patch-actions">
                                <button class="agent__btn agent__btn--primary" type="button" :disabled="applying" @click="apply(message)">Apply changes</button>
                                <button class="agent__btn" type="button" :disabled="applying" @click="dismiss(message)">Dismiss</button>
                            </div>
                        </div>

                        <ul v-if="(message.warnings || []).length" class="agent__warnings">
                            <li v-for="(w, i) in message.warnings" :key="i">{{ w }}</li>
                        </ul>
                    </template>
                </div>
            </div>

            <div v-if="activeSession && activeSession.archived" class="agent__archived-note">
                This session is archived.
                <button class="agent__link" type="button" :disabled="!canEdit" @click="unarchive">Unarchive</button>
            </div>

            <form v-else-if="canEdit" class="agent__composer" @submit.prevent="send">
                <textarea
                    v-model="input"
                    class="agent__input"
                    rows="2"
                    placeholder="Describe a change…"
                    :disabled="sending"
                    @keydown.enter.exact.prevent="send"
                ></textarea>
                <div class="agent__composer-actions">
                    <button v-if="activeSession" class="agent__link" type="button" :disabled="sending" @click="archive">Archive</button>
                    <span class="agent__spacer"></span>
                    <button class="agent__btn agent__btn--primary" type="submit" :disabled="sending || !input.trim()">
                        {{ sending ? 'Thinking…' : 'Send' }}
                    </button>
                </div>
            </form>

            <p v-else class="agent__notice agent__notice--small">You have read-only access. Sessions are visible but you can’t send prompts.</p>
        </template>
    </aside>
</template>

<script setup>
import { computed, nextTick, onMounted, ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import SvgIcon from '../SvgIcon.vue'
import { DiagramAgent } from '@/services/DiagramAgent.js'
import { foundryErrorMessage } from '@/services/Foundry.js'
import { operationLabel, isDestructive } from '@/services/diagramAgentPatch.js'

const props = defineProps({
    diagramId: { type: Number, default: null },
    isOntology: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: false },
    // (operations, { allowDestructive }) => { applied, failed, warnings }
    apply: { type: Function, required: true },
    // () => boolean — reverts the diagram's last change (agent apply). Optional.
    undo: { type: Function, default: null },
})

defineEmits(['close'])

const $toast = useToast()

const loadingModels = ref(true)
const enabled = ref(false)
const models = ref([])
const selectedModel = ref(null)

const sessions = ref([])
const includeArchived = ref(false)
const activeSession = ref(null)
const messages = ref([])

const input = ref('')
const allowDestructive = ref(false)
const sending = ref(false)
const applying = ref(false)
const appliedIds = ref(new Set())
// The message whose apply can still be reverted with the diagram's single-step
// undo (i.e. the most recent apply in this panel session).
const undoableId = ref(null)
const scrollRef = ref(null)

const activeSessionId = computed(() => activeSession.value?.id ?? null)

const patchOps = (message) => message?.patch?.operations ?? []
const hasDestructive = (message) => patchOps(message).some(isDestructive)

// Persisted "applied" flags come back on each message; merge them in so a closed
// then reopened panel won't offer to apply (and duplicate) an applied patch.
function syncAppliedFromMessages() {
    const next = new Set(appliedIds.value)
    for (const message of messages.value) {
        if (message.applied) next.add(message.id)
    }
    appliedIds.value = next
}

async function loadModels() {
    loadingModels.value = true
    try {
        const data = await DiagramAgent.models(props.diagramId)
        enabled.value = !!data.enabled
        models.value = data.data ?? []
        selectedModel.value = data.default_model ?? models.value[0]?.model ?? null
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not load agent models.'))
    } finally {
        loadingModels.value = false
    }
}

async function loadSessions() {
    try {
        sessions.value = await DiagramAgent.listSessions(props.diagramId, includeArchived.value)
        if (!activeSession.value && sessions.value.length) {
            await onSelectSession(sessions.value[0].id)
        }
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not load agent sessions.'))
    }
}

async function onSelectSession(id) {
    if (!id) {
        activeSession.value = null
        messages.value = []
        return
    }
    try {
        const session = await DiagramAgent.getSession(props.diagramId, id)
        activeSession.value = session
        messages.value = session.messages ?? []
        undoableId.value = null
        syncAppliedFromMessages()
        scrollToBottom()
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not open the session.'))
    }
}

async function newSession() {
    if (!props.canEdit) return
    try {
        const session = await DiagramAgent.createSession(props.diagramId, { model: selectedModel.value })
        sessions.value = [session, ...sessions.value]
        activeSession.value = session
        messages.value = []
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not start a session.'))
    }
}

async function send() {
    const text = input.value.trim()
    if (!text || sending.value) return
    sending.value = true
    try {
        if (!activeSession.value) {
            const session = await DiagramAgent.createSession(props.diagramId, { model: selectedModel.value })
            sessions.value = [session, ...sessions.value]
            activeSession.value = session
            messages.value = []
        }
        input.value = ''
        await DiagramAgent.sendMessage(props.diagramId, activeSession.value.id, text, {
            model: selectedModel.value,
            allowDestructive: allowDestructive.value,
        })
        await refreshActive()
    } catch (error) {
        // Persisted failed turns still show after refresh; surface the reason too.
        await refreshActive()
        $toast.error(error.foundryCode ? error.message : foundryErrorMessage(error, 'The agent request failed.'))
    } finally {
        sending.value = false
    }
}

async function refreshActive() {
    if (!activeSession.value) return
    const session = await DiagramAgent.getSession(props.diagramId, activeSession.value.id)
    activeSession.value = session
    messages.value = session.messages ?? []
    syncAppliedFromMessages()
    // Keep session list titles fresh.
    const idx = sessions.value.findIndex(s => s.id === session.id)
    if (idx !== -1) sessions.value[idx] = { ...sessions.value[idx], title: session.title, last_message_at: session.last_message_at }
    scrollToBottom()
}

async function apply(message) {
    if (applying.value) return
    applying.value = true
    try {
        const summary = await props.apply(patchOps(message), { allowDestructive: allowDestructive.value })
        // The diagram is now mutated and saved. Guard locally immediately, then
        // persist the applied flag so a refresh/reopen can't duplicate it.
        appliedIds.value = new Set([...appliedIds.value, message.id])
        undoableId.value = message.id
        try {
            await DiagramAgent.markApplied(props.diagramId, message.session_id, message.id)
        } catch {
            $toast.error('Applied, but recording it failed — reload before applying again to avoid duplicates.')
        }
        const failed = summary?.failed?.length ?? 0
        $toast.success(`Applied ${summary?.applied?.length ?? 0} change${(summary?.applied?.length ?? 0) === 1 ? '' : 's'}${failed ? `, ${failed} skipped` : ''}.`)
    } catch (error) {
        $toast.error(error?.message || 'Could not apply the changes.')
    } finally {
        applying.value = false
    }
}

async function undoApply(message) {
    if (applying.value || !props.undo) return
    applying.value = true
    try {
        const reverted = await props.undo()
        if (reverted === false) {
            $toast.error('Nothing to undo.')
            return
        }
        const next = new Set(appliedIds.value)
        next.delete(message.id)
        appliedIds.value = next
        undoableId.value = null
        try {
            await DiagramAgent.unmarkApplied(props.diagramId, message.session_id, message.id)
        } catch { /* non-fatal */ }
        $toast.success('Reverted the applied changes.')
    } catch (error) {
        $toast.error(error?.message || 'Could not undo the changes.')
    } finally {
        applying.value = false
    }
}

function dismiss(message) {
    appliedIds.value = new Set([...appliedIds.value, message.id])
}

async function archive() {
    if (!activeSession.value) return
    try {
        await DiagramAgent.archiveSession(props.diagramId, activeSession.value.id)
        $toast.success('Session archived.')
        activeSession.value = null
        messages.value = []
        await loadSessions()
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not archive the session.'))
    }
}

async function unarchive() {
    if (!activeSession.value) return
    try {
        const session = await DiagramAgent.unarchiveSession(props.diagramId, activeSession.value.id)
        activeSession.value = { ...activeSession.value, ...session }
        await loadSessions()
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not unarchive the session.'))
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (scrollRef.value) scrollRef.value.scrollTop = scrollRef.value.scrollHeight
    })
}

onMounted(async () => {
    if (!props.diagramId || !props.isOntology) {
        loadingModels.value = false
        return
    }
    await loadModels()
    if (enabled.value) await loadSessions()
})
</script>

<style scoped>
.agent {
    position: fixed;
    top: 48px;
    right: 0;
    bottom: 0;
    width: 360px;
    max-width: 92vw;
    display: flex;
    flex-direction: column;
    background: var(--bg-surface);
    border-left: 1px solid var(--border-color);
    box-shadow: -8px 0 24px rgba(0, 0, 0, 0.18);
    z-index: 60;
}

.agent__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-color);
}

.agent__title {
    display: flex;
    align-items: center;
    gap: 7px;
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--text-primary);
}

.agent__icon-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    padding: 3px;
    border-radius: 5px;
}

.agent__icon-btn:hover:not(:disabled) { color: var(--text-primary); background: var(--hover-bg-alt); }
.agent__icon-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.agent__notice {
    padding: 16px;
    font-size: 0.78rem;
    color: var(--text-muted);
    line-height: 1.5;
}

.agent__notice--small { padding: 10px 12px; border-top: 1px solid var(--border-color); }

.agent__bar {
    display: flex;
    gap: 6px;
    align-items: center;
    padding: 8px 12px 4px;
}

.agent__select {
    height: 30px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 0.74rem;
    padding: 0 6px;
    flex: 1;
    min-width: 0;
}

.agent__select--session { flex: 1.2; }

.agent__archived-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px 6px;
    font-size: 0.7rem;
    color: var(--text-muted);
}

.agent__messages {
    flex: 1;
    overflow-y: auto;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.agent__hint { font-size: 0.76rem; color: var(--text-muted); line-height: 1.5; }

.agent__msg { display: flex; flex-direction: column; gap: 3px; }
.agent__msg--user { align-items: flex-end; }

.agent__msg-meta {
    display: flex;
    gap: 6px;
    font-size: 0.64rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.agent__msg-model { opacity: 0.7; }

.agent__bubble {
    max-width: 90%;
    padding: 8px 10px;
    border-radius: 10px;
    font-size: 0.8rem;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
    background: var(--bg-surface-alt);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.agent__msg--user .agent__bubble {
    background: var(--color-primary);
    color: var(--color-text-on-primary);
    border-color: var(--color-primary);
}

.agent__bubble--error {
    background: #fdf0f0;
    border-color: #e0b0b0;
    color: #8f2f2f;
}

.agent__patch {
    margin-top: 6px;
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 8px;
    background: var(--bg-surface);
}

.agent__patch-head {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.agent__patch-head span {
    background: var(--hover-bg-alt);
    border-radius: 8px;
    padding: 0 6px;
    font-size: 0.66rem;
}

.agent__patch-list {
    list-style: none;
    margin: 0 0 6px;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.agent__patch-list li {
    font-size: 0.75rem;
    color: var(--text-primary);
    padding-left: 12px;
    position: relative;
}

.agent__patch-list li::before {
    content: '+';
    position: absolute;
    left: 0;
    color: #2e7d52;
}

.agent__patch-list li.is-destructive::before { content: '−'; color: #c0392b; }

.agent__destructive { font-size: 0.72rem; color: #b9770e; margin-bottom: 6px; }
.agent__destructive label { display: flex; gap: 6px; align-items: center; }

.agent__applied {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.74rem;
    color: var(--color-primary-text);
}

.agent__patch-actions { display: flex; gap: 6px; }

.agent__warnings {
    margin: 6px 0 0;
    padding-left: 16px;
    font-size: 0.7rem;
    color: #b9770e;
}

.agent__btn {
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: transparent;
    color: var(--text-primary);
    font-size: 0.74rem;
    cursor: pointer;
}

.agent__btn:hover:not(:disabled) { background: var(--hover-bg-alt); }
.agent__btn:disabled { opacity: 0.6; cursor: not-allowed; }

.agent__btn--primary {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: var(--color-text-on-primary);
}

.agent__composer {
    border-top: 1px solid var(--border-color);
    padding: 8px 12px 10px;
}

.agent__input {
    width: 100%;
    resize: vertical;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--input-bg);
    color: var(--text-primary);
    font-size: 0.8rem;
    padding: 8px;
    font-family: inherit;
}

.agent__composer-actions { display: flex; align-items: center; margin-top: 6px; }
.agent__spacer { flex: 1; }

.agent__link {
    background: none;
    border: none;
    color: var(--color-primary-text);
    font-size: 0.72rem;
    cursor: pointer;
    text-decoration: underline;
    padding: 0;
}

.agent__link:disabled { opacity: 0.5; cursor: not-allowed; }

.agent__archived-note {
    border-top: 1px solid var(--border-color);
    padding: 10px 12px;
    font-size: 0.74rem;
    color: var(--text-muted);
    display: flex;
    gap: 8px;
    align-items: center;
}
</style>
