<template>
    <section class="foundry-section">
        <div class="foundry-section__heading">
            <h3>Foundry <span v-if="status">{{ stateBadge }}</span></h3>
            <button class="foundry-refresh" type="button" title="Refresh Foundry status" @click="reload">
                <SvgIcon name="refresh" :size="13" />
            </button>
        </div>

        <p v-if="!diagramId" class="foundry-empty">Save the diagram to enable Foundry.</p>

        <template v-else>
            <div v-if="loading" class="foundry-empty">Loading…</div>

            <template v-else>
                <label class="foundry-field">
                    <span>Host</span>
                    <input
                        v-model="hostInput"
                        type="text"
                        :disabled="!canManageHost"
                        placeholder="acme.palantirfoundry.com"
                        @keydown.enter.prevent="saveHost"
                    />
                </label>
                <div v-if="canManageHost" class="foundry-actions">
                    <button type="button" class="foundry-btn" :disabled="saving" @click="saveHost">
                        {{ saving ? 'Saving…' : 'Save host' }}
                    </button>
                </div>

                <p class="foundry-state" :class="`foundry-state--${status?.state || 'unknown'}`">{{ stateMessage }}</p>

                <div class="foundry-actions">
                    <button v-if="canConnect" type="button" class="foundry-btn foundry-btn--primary" :disabled="busy" @click="connect">
                        {{ status?.state === 'expired' ? 'Reconnect' : 'Connect' }}
                    </button>
                    <button v-if="isConnected" type="button" class="foundry-btn" :disabled="busy" @click="disconnect">
                        Disconnect
                    </button>
                </div>

                <template v-if="showTokenOption">
                    <button type="button" class="foundry-link" @click="showTokenForm = !showTokenForm">
                        {{ showTokenForm ? 'Hide token option' : 'Use a Foundry token instead' }}
                    </button>
                    <div v-if="showTokenForm" class="foundry-token">
                        <label class="foundry-field">
                            <span>Foundry token</span>
                            <input v-model="tokenInput" type="password" placeholder="Paste a Foundry token" autocomplete="off" />
                        </label>
                        <label class="foundry-field">
                            <span>Expires (optional)</span>
                            <input v-model="tokenExpiry" type="datetime-local" />
                        </label>
                        <div class="foundry-actions">
                            <button type="button" class="foundry-btn foundry-btn--primary" :disabled="tokenBusy || !tokenInput" @click="connectWithToken">
                                {{ tokenBusy ? 'Connecting…' : 'Connect with token' }}
                            </button>
                        </div>
                    </div>
                </template>

                <p v-if="isConnected" class="foundry-hint">Use the Foundry button in the top toolbar to browse ontologies and datasets.</p>
            </template>
        </template>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toast-notification'
import { Foundry, foundryErrorMessage } from '@/services/Foundry.js'
import SvgIcon from '../SvgIcon.vue'

const props = defineProps({
    diagramId: { type: Number, default: null },
    canManageHost: { type: Boolean, default: false },
})

const $toast = useToast()

const loading = ref(false)
const saving = ref(false)
const busy = ref(false)
const tokenBusy = ref(false)
const showTokenForm = ref(false)

const status = ref(null)
const hostInput = ref('')
const tokenInput = ref('')
const tokenExpiry = ref('')

const isConnected = computed(() => status.value?.state === 'connected')
const canConnect = computed(() => !!status.value?.connectable && !isConnected.value)
const showTokenOption = computed(() =>
    !!status.value?.allow_token_auth && status.value?.state !== 'host_not_set' && !isConnected.value,
)

const stateBadge = computed(() => (isConnected.value ? 'Connected' : 'Not connected'))

const stateMessage = computed(() => {
    switch (status.value?.state) {
        case 'connected': {
            const via = status.value.auth_type === 'token' ? 'Connected via token' : 'Connected'
            return status.value.display_name ? `${via} as ${status.value.display_name}.` : `${via}.`
        }
        case 'expired':
            return 'Your Foundry connection expired. Reconnect to continue.'
        case 'disconnected':
            return 'Connect your Foundry account to browse datasets.'
        case 'host_not_configured':
            return 'This host needs administrator OAuth setup before anyone can connect.'
        case 'host_not_set':
            return props.canManageHost ? 'Set a Foundry host to enable the connection.' : 'No Foundry host set for this diagram.'
        default:
            return ''
    }
})

async function reload() {
    if (!props.diagramId) return
    loading.value = true
    try {
        const [config, statusData] = await Promise.all([
            Foundry.getConfig(props.diagramId),
            Foundry.status(props.diagramId),
        ])
        hostInput.value = config?.host_url ?? ''
        status.value = statusData
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not load Foundry status.'))
    } finally {
        loading.value = false
    }
}

async function saveHost() {
    if (!props.canManageHost || saving.value) return
    saving.value = true
    try {
        await Foundry.updateConfig(props.diagramId, { host_url: hostInput.value.trim() || null })
        $toast.success('Foundry host saved.')
        await reload()
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not save the Foundry host.'))
    } finally {
        saving.value = false
    }
}

async function connect() {
    busy.value = true
    try {
        const { authorize_url: authorizeUrl } = await Foundry.authorize(props.diagramId)
        if (authorizeUrl) window.location.href = authorizeUrl
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not start Foundry authorization.'))
        busy.value = false
    }
}

async function connectWithToken() {
    if (!tokenInput.value || tokenBusy.value) return
    tokenBusy.value = true
    try {
        const expires = tokenExpiry.value ? new Date(tokenExpiry.value).toISOString() : null
        await Foundry.connectWithToken(props.diagramId, tokenInput.value.trim(), expires)
        $toast.success('Foundry connected with token.')
        tokenInput.value = ''
        tokenExpiry.value = ''
        showTokenForm.value = false
        await reload()
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not connect with token.'))
    } finally {
        tokenBusy.value = false
    }
}

async function disconnect() {
    busy.value = true
    try {
        const connections = await Foundry.connections()
        const match = connections.find((c) => c.host_url === status.value?.host_url)
        if (match) await Foundry.disconnect(match.id)
        $toast.success('Foundry disconnected.')
        await reload()
    } catch (error) {
        $toast.error(foundryErrorMessage(error, 'Could not disconnect.'))
    } finally {
        busy.value = false
    }
}

onMounted(reload)
</script>

<style scoped>
.foundry-section {
    padding-top: 12px;
    margin-top: 4px;
    border-top: 1px solid var(--border-color);
}

.foundry-section__heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.foundry-section h3 {
    margin: 0 0 8px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-secondary);
}

.foundry-section h3 span {
    margin-left: 6px;
    font-size: 0.68rem;
    font-weight: 600;
    text-transform: none;
    color: var(--text-muted);
}

.foundry-refresh {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    padding: 2px;
}

.foundry-refresh:hover {
    color: var(--text-secondary);
}

.foundry-field {
    display: flex;
    flex-direction: column;
    gap: 3px;
    margin-bottom: 8px;
    font-size: 0.7rem;
    color: var(--text-muted);
}

.foundry-field input {
    height: 32px;
    padding: 0 10px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 13px;
    background: var(--input-bg);
    color: var(--text-primary);
}

.foundry-field input:focus {
    outline: none;
    border-color: var(--border-strong);
}

.foundry-field input:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.foundry-actions {
    display: flex;
    gap: 6px;
    margin-bottom: 8px;
}

.foundry-btn {
    padding: 6px 12px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: transparent;
    color: var(--text-primary);
    font-size: 0.75rem;
    cursor: pointer;
}

.foundry-btn:hover:not(:disabled) {
    background: var(--hover-bg-alt);
    border-color: var(--border-strong);
}

.foundry-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.foundry-btn--primary {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: var(--color-text-on-primary);
}

.foundry-btn--primary:hover:not(:disabled) {
    background: var(--color-primary-hover);
    border-color: var(--color-primary-hover);
}

.foundry-link {
    background: none;
    border: none;
    padding: 0;
    margin: 0 0 8px;
    color: var(--color-primary-text);
    font-size: 0.72rem;
    cursor: pointer;
    text-decoration: underline;
}

.foundry-token {
    padding: 8px;
    margin-bottom: 8px;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    background: var(--bg-surface-alt);
}

.foundry-state {
    margin: 0 0 8px;
    font-size: 0.72rem;
    color: var(--text-muted);
}

.foundry-state--connected {
    color: var(--color-primary-text);
}

.foundry-state--expired,
.foundry-state--host_not_configured {
    color: #d6a35c;
}

.foundry-empty {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin: 0 0 8px;
}

.foundry-hint {
    margin: 4px 0 8px;
    font-size: 0.7rem;
    color: var(--text-muted);
    line-height: 1.4;
}
</style>
