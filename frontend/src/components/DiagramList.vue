<template>
    <div class="diagrams-page">
        <div class="diagrams-header">
            <h2 class="diagrams-title">Diagrams</h2>
        </div>

        <div class="diagrams-grid-container">
            <section class="diagram-section">
                <div class="diagram-section__header">
                    <button class="diagram-section__toggle" type="button" @click="toggleSection('owned')">
                        <span>{{ collapsedSections.owned ? '▸' : '▾' }}</span>
                        <h3>My Diagrams</h3>
                        <small>{{ filteredOwnedDiagrams.length }}</small>
                    </button>
                    <input v-model="sectionSearch.owned" class="diagram-section__search" type="search" placeholder="Search my diagrams" />
                </div>
                <div v-show="!collapsedSections.owned" class="diagrams-grid">
                    <!-- New diagram card -->
                    <div class="diagram-card diagram-card--new" @click="openNewForm">
                        <div class="diagram-card__preview diagram-card__preview--empty">
                            <SvgIcon name="plus" :size="40" class="new-diagram-plus" />
                        </div>
                        <div class="diagram-card__footer">
                            <span class="diagram-card__name">New Diagram</span>
                        </div>
                    </div>

                    <div
                        v-for="diagram in filteredOwnedDiagrams"
                        :key="`owned-${diagram.id}`"
                        class="diagram-card"
                        @click="viewDiagram(diagram.share_token)"
                    >
                        <div class="diagram-card__preview">
                            <DiagramPreview :schema="diagram.schema" />
                            <span
                                v-if="visibilityStatus(diagram)"
                                class="diagram-card__visibility"
                                :class="`diagram-card__visibility--${visibilityStatus(diagram).kind}`"
                                :title="visibilityStatus(diagram).title"
                                :aria-label="visibilityStatus(diagram).title"
                            >
                                <SvgIcon :name="visibilityStatus(diagram).icon" :size="14" />
                            </span>
                            <button
                                class="diagram-card__delete"
                                @click.stop="deleteDiagram(diagram.id)"
                                title="Delete"
                            >
                                <SvgIcon name="trash" :size="14" />
                            </button>
                        </div>
                        <div class="diagram-card__footer">
                            <img
                                :src="dbIcons[diagram.db_type] || dbIcons.mysql"
                                :alt="diagram.db_type"
                                class="diagram-card__db-icon"
                            />
                            <input
                                v-if="renamingId === diagram.id"
                                ref="renameInput"
                                v-model="diagram.name"
                                class="diagram-card__name-input"
                                @click.stop
                                @keyup.enter="commitRename(diagram)"
                                @keyup.escape="cancelRename(diagram)"
                                @blur="commitRename(diagram)"
                            />
                            <span
                                v-else
                                class="diagram-card__name"
                                title="Open diagram"
                            >{{ diagram.name }}</span>
                            <button
                                v-if="renamingId !== diagram.id"
                                class="diagram-card__rename"
                                @click.stop="startRename(diagram)"
                                title="Rename"
                            >
                                <SvgIcon name="edit" :size="13" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="diagram-section">
                <div class="diagram-section__header">
                    <button class="diagram-section__toggle" type="button" @click="toggleSection('shared')">
                        <span>{{ collapsedSections.shared ? '▸' : '▾' }}</span>
                        <h3>Shared With Me</h3>
                        <small>{{ filteredSharedDiagrams.length }}</small>
                    </button>
                    <input v-model="sectionSearch.shared" class="diagram-section__search" type="search" placeholder="Search shared diagrams" />
                </div>
                <div v-show="!collapsedSections.shared" class="diagrams-grid">
                    <p v-if="!filteredSharedDiagrams.length" class="diagram-section__empty">No shared diagrams found.</p>
                    <div
                        v-for="diagram in filteredSharedDiagrams"
                        :key="`shared-${diagram.id}`"
                        class="diagram-card"
                        @click="viewDiagram(diagram.share_token)"
                    >
                        <div class="diagram-card__preview">
                            <DiagramPreview :schema="diagram.schema" />
                            <span
                                v-if="visibilityStatus(diagram)"
                                class="diagram-card__visibility"
                                :class="`diagram-card__visibility--${visibilityStatus(diagram).kind}`"
                                :title="visibilityStatus(diagram).title"
                                :aria-label="visibilityStatus(diagram).title"
                            >
                                <SvgIcon :name="visibilityStatus(diagram).icon" :size="14" />
                            </span>
                            <span class="diagram-card__badge">{{ accessLabel(diagram) }}</span>
                            <button class="diagram-card__duplicate" @click.stop="duplicateDiagram(diagram)" title="Duplicate">
                                <SvgIcon name="copy" :size="13" />
                            </button>
                        </div>
                        <div class="diagram-card__footer">
                            <img :src="dbIcons[diagram.db_type] || dbIcons.mysql" :alt="diagram.db_type" class="diagram-card__db-icon" />
                            <span class="diagram-card__name" :title="diagram.owner_email ? `Shared by ${diagram.owner_email}` : 'Shared diagram'">{{ diagram.name }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="diagram-section">
                <div class="diagram-section__header">
                    <button class="diagram-section__toggle" type="button" @click="toggleSection('public')">
                        <span>{{ collapsedSections.public ? '▸' : '▾' }}</span>
                        <h3>Company Wide Diagrams</h3>
                        <small>{{ filteredPublicDiagrams.length }}</small>
                    </button>
                    <input v-model="sectionSearch.public" class="diagram-section__search" type="search" placeholder="Search company diagrams" />
                </div>
                <div v-show="!collapsedSections.public" class="diagrams-grid">
                    <p v-if="!filteredPublicDiagrams.length" class="diagram-section__empty">No company wide diagrams found.</p>
                    <div
                        v-for="diagram in filteredPublicDiagrams"
                        :key="`public-${diagram.id}`"
                        class="diagram-card"
                        @click="viewDiagram(diagram.share_token)"
                    >
                        <div class="diagram-card__preview">
                            <DiagramPreview :schema="diagram.schema" />
                            <span
                                v-if="visibilityStatus(diagram)"
                                class="diagram-card__visibility"
                                :class="`diagram-card__visibility--${visibilityStatus(diagram).kind}`"
                                :title="visibilityStatus(diagram).title"
                                :aria-label="visibilityStatus(diagram).title"
                            >
                                <SvgIcon :name="visibilityStatus(diagram).icon" :size="14" />
                            </span>
                            <span class="diagram-card__badge">{{ accessLabel(diagram) }}</span>
                            <button class="diagram-card__duplicate" @click.stop="duplicateDiagram(diagram)" title="Duplicate">
                                <SvgIcon name="copy" :size="13" />
                            </button>
                        </div>
                        <div class="diagram-card__footer">
                            <img :src="dbIcons[diagram.db_type] || dbIcons.mysql" :alt="diagram.db_type" class="diagram-card__db-icon" />
                            <span class="diagram-card__name" :title="diagram.owner_email ? `Published by ${diagram.owner_email}` : 'Company wide diagram'">{{ diagram.name }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <p v-if="!ownedDiagrams.length && !sharedDiagrams.length && !publicDiagrams.length" class="diagrams-empty-state">
                Create your first diagram or browse shared company diagrams when your coworkers publish them.
            </p>
        </div>

        <!-- New Diagram Modal -->
        <div v-if="showNewForm" class="create-modal-overlay" @click.self="showNewForm = false">
            <div class="create-modal">

                <div class="create-modal__header">
                    <span class="create-modal__title">New Diagram</span>
                    <button class="create-modal__close" @click="showNewForm = false" title="Close">
                        <SvgIcon name="close" :size="14" />
                    </button>
                </div>

                <div class="create-modal__body">
                    <div class="create-modal__field">
                        <span class="create-modal__label">Name</span>
                        <input
                            ref="newNameInput"
                            v-model="newDiagramName"
                            class="create-modal__input"
                            placeholder="My diagram"
                            @keyup.enter="addDiagram"
                            @keyup.escape="showNewForm = false"
                        />
                    </div>

                    <div class="create-modal__field">
                        <span class="create-modal__label">Database</span>
                        <div class="create-modal__db-options">
                            <button
                                v-for="db in dbOptions"
                                :key="db.type"
                                class="db-option"
                                :class="{ 'db-option--active': newDiagramDbType === db.type }"
                                @click="newDiagramDbType = db.type"
                                :title="db.label"
                            >
                                <img :src="db.icon || dbIcons[db.type]" :alt="db.label" />
                                <span>{{ db.label }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="create-modal__field">
                        <span class="create-modal__label">Visibility</span>
                        <div class="create-modal__vis-row">
                            <div class="create-modal__vis-chips">
                                <button
                                    class="create-modal__vis-btn"
                                    :class="{ 'create-modal__vis-btn--active': newDiagramPublic }"
                                    @click="newDiagramPublic = true"
                                >Public</button>
                                <button
                                    class="create-modal__vis-btn"
                                    :class="{ 'create-modal__vis-btn--active': !newDiagramPublic }"
                                    @click="newDiagramPublic = false"
                                >Private</button>
                            </div>
                            <template v-if="newDiagramPublic">
                                <span class="create-modal__help-icon">
                                    ?
                                    <span class="create-modal__tooltip">Public diagrams appear in Company Wide Diagrams for logged-in users. They are read-only unless you enable edit access later.</span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="create-modal__footer">
                    <button class="create-modal__btn create-modal__btn--cancel" @click="showNewForm = false">Cancel</button>
                    <button class="create-modal__btn create-modal__btn--create" @click="addDiagram">Create</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios'
import router from '../router/index.js'
import { useToast } from 'vue-toast-notification'
import { Diagram as DiagramApi } from '../services/Diagram.js'
import DiagramPreview from './Diagram/DiagramPreview.vue'
import SvgIcon from './SvgIcon.vue'
import mysqlIcon from '../icons/mysql.svg'
import postgresqlIcon from '../icons/postgresql.svg'
import sqliteIcon from '../icons/sqlite.svg'
import oracleIcon from '../icons/oracle.svg'
import sqlserverIcon from '../icons/sqlserver.svg'
import msaccessIcon from '../icons/msaccess.svg'

const $toast = useToast()

export default {
    components: { DiagramPreview, SvgIcon },
    data() {
        return {
            ownedDiagrams: [],
            sharedDiagrams: [],
            publicDiagrams: [],
            collapsedSections: { owned: false, shared: false, public: true },
            sectionSearch: { owned: '', shared: '', public: '' },
            newDiagramName: '',
            newDiagramDbType: 'ontology',
            newDiagramPublic: false,
            showNewForm: false,
            renamingId: null,
            originalName: null,
            dbIcons: { mysql: mysqlIcon, postgresql: postgresqlIcon, sqlite: sqliteIcon, oracle: oracleIcon, sqlserver: sqlserverIcon, msaccess: msaccessIcon, ontology: '/palantir.svg' },
            dbOptions: [
                { type: 'ontology', label: 'Ontology', icon: '/palantir.svg' },
                { type: 'mysql', label: 'MySQL' },
                { type: 'postgresql', label: 'PostgreSQL' },
                { type: 'sqlite', label: 'SQLite' },
                { type: 'oracle', label: 'Oracle' },
                { type: 'sqlserver', label: 'SQL Server' },
                { type: 'msaccess', label: 'MS Access' },
            ]
        }
    },
    computed: {
        filteredOwnedDiagrams() {
            return this.filterDiagrams(this.ownedDiagrams, this.sectionSearch.owned)
        },
        filteredSharedDiagrams() {
            return this.filterDiagrams(this.sharedDiagrams, this.sectionSearch.shared)
        },
        filteredPublicDiagrams() {
            return this.filterDiagrams(this.publicDiagrams, this.sectionSearch.public)
        }
    },
    methods: {
        filterDiagrams(diagrams, query) {
            const q = query.trim().toLowerCase()
            if (!q) return diagrams
            return diagrams.filter(diagram => [diagram.name, diagram.owner_email, diagram.db_type]
                .filter(Boolean)
                .some(value => String(value).toLowerCase().includes(q)))
        },
        toggleSection(section) {
            this.collapsedSections[section] = !this.collapsedSections[section]
        },
        viewDiagram(token) {
            router.push({ name: 'diagram.show', params: { token } })
        },
        accessLabel(diagram) {
            return diagram.effective_access === 'write' ? 'Can edit' : 'Read-only'
        },
        visibilityStatus(diagram) {
            if (diagram.library) {
                return {
                    kind: 'public',
                    icon: 'globe',
                    title: diagram.effective_access === 'write' || diagram.share_access === 'write'
                        ? 'Company-wide diagram: others can edit'
                        : 'Company-wide diagram: others can view'
                }
            }
            if (diagram.share_access || diagram.effective_access) {
                return {
                    kind: 'shared',
                    icon: 'share',
                    title: diagram.effective_access === 'write' || diagram.share_access === 'write'
                        ? 'Shared diagram: others can edit'
                        : 'Shared diagram: restricted access'
                }
            }
            return null
        },
        openNewForm() {
            this.showNewForm = true
            this.$nextTick(() => this.$refs.newNameInput?.focus())
        },
        async addDiagram() {
            if (!this.newDiagramName.trim()) {
                $toast.error('Diagram name cannot be empty.')
                return
            }
            try {
                const response = await axios.post('/api/diagrams', {
                    name: this.newDiagramName,
                    db_type: this.newDiagramDbType,
                    share_access: this.newDiagramPublic ? 'read' : null,
                    library: this.newDiagramPublic
                })
                this.newDiagramName = ''
                this.newDiagramDbType = 'mysql'
                this.newDiagramPublic = true
                this.showNewForm = false
                $toast.success(response.data.message)
                await router.push({
                    name: 'diagram.show',
                    params: { token: response.data.diagram.share_token }
                })
            } catch (error) {
                const errors = error.response?.data?.errors
                if (errors?.name) {
                    $toast.error(`A diagram named "${this.newDiagramName}" already exists.`)
                } else {
                    $toast.error(error.response?.data?.message ?? 'Something went wrong!')
                }
            }
        },
        async updateDiagram(diagram) {
            const response = await axios.put(`/api/diagrams/${diagram.id}`, { name: diagram.name })
            await this.fetchDiagrams()
            this.originalName = null
            response.status ? $toast.success(response.data.message) : $toast.error(response.data.message)
        },
        async deleteDiagram(id) {
            const response = await axios.delete(`/api/diagrams/${id}`)
            await this.fetchDiagrams()
            response.status ? $toast.success(response.data.message) : $toast.error(response.data.message)
        },
        async duplicateDiagram(diagram) {
            const copy = await DiagramApi.duplicateByToken(diagram.share_token)
            if (copy?.share_token) {
                $toast.success('Diagram duplicated')
                await router.push({ name: 'diagram.show', params: { token: copy.share_token } })
            }
        },
        startRename(diagram) {
            this.originalName = diagram.name
            this.renamingId = diagram.id
            this.$nextTick(() => {
                const input = this.$refs.renameInput
                const el = Array.isArray(input) ? input[0] : input
                el?.focus()
                el?.select()
            })
        },
        commitRename(diagram) {
            if (this.renamingId !== diagram.id) return
            this.renamingId = null
            if (diagram.name !== this.originalName) {
                this.updateDiagram(diagram)
            }
        },
        cancelRename(diagram) {
            diagram.name = this.originalName
            this.renamingId = null
            this.originalName = null
        },
        async fetchDiagrams() {
            try {
                const response = await axios.get('/api/diagrams/dashboard')
                this.ownedDiagrams = response.data.owned ?? []
                this.sharedDiagrams = response.data.shared ?? []
                this.publicDiagrams = response.data.public ?? []
            } catch (error) {
                if (error.response) {
                    $toast.error(error.response.data.message)
                } else {
                    $toast.error('Something went wrong!')
                }
            }
        }
    },
    created() {
        this.fetchDiagrams()
    }
}
</script>

<style scoped>
.diagrams-page {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--bg-elevated);
}

.diagrams-header {
    flex-shrink: 0;
    padding: 1.25rem 2rem;
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border-light);
}

.diagrams-title {
    margin: 0;
    color: var(--color-primary-text);
    font-size: 1rem;
    letter-spacing: 1px;
}

.diagrams-grid-container {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 2rem;
}

.diagram-section__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.5rem 2rem 0;
}

.diagram-section__toggle {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0;
    color: var(--text-secondary);
    cursor: pointer;
    background: transparent;
    border: 0;
}

.diagram-section__header h3 {
    margin: 0;
    color: inherit;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.diagram-section__header small {
    color: var(--text-muted);
    font-size: 0.72rem;
}

.diagram-section__search {
    width: min(260px, 45vw);
    padding: 0.45rem 0.6rem;
    color: var(--text-primary);
    background: var(--input-bg);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    outline: none;
}

.diagram-section__search:focus {
    border-color: var(--color-primary);
}

.diagrams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
}

.diagram-section + .diagram-section .diagrams-grid {
    padding-top: 1rem;
}

.diagrams-empty-state {
    margin: 2rem;
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.diagram-section__empty {
    grid-column: 1 / -1;
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.85rem;
}

@media (max-width: 480px) {
    .diagrams-grid {
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        padding: 1rem;
    }

    .diagrams-header {
        padding: 1rem;
    }

    .diagram-section__header {
        align-items: stretch;
        flex-direction: column;
        gap: 0.75rem;
        padding: 1.25rem 1rem 0;
    }

    .diagram-section__search {
        width: 100%;
    }
}

/* ── Cards ─────────────────────────────────────────────────── */
.diagram-card {
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    cursor: pointer;
    transition: box-shadow 0.2s, transform 0.2s;
    position: relative;
}

.diagram-card:hover {
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35);
    transform: translateY(-2px);
}

.diagram-card--new {
    border: 2px dashed var(--border-color);
    box-shadow: none;
    background: transparent;
}

.diagram-card--new:hover {
    border-color: var(--color-primary);
    background: var(--bg-surface);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
    transform: translateY(-2px);
}

/* ── Card preview area ──────────────────────────────────────── */
.diagram-card__preview {
    height: 160px;
    background: var(--bg-page);
    border-bottom: 1px solid var(--border-color);
    padding: 8px;
    position: relative;
    overflow: hidden;
}

.diagram-card__preview--empty {
    display: flex;
    align-items: center;
    justify-content: center;
}

.new-diagram-plus {
    opacity: 0.25;
    color: var(--text-primary);
    transition: opacity 0.2s;
}

.diagram-card--new:hover .new-diagram-plus {
    opacity: 0.55;
}

/* ── Delete button (visible on hover) ───────────────────────── */
.diagram-card__delete {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 26px;
    height: 26px;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: 50%;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 4px;
    color: var(--text-muted);
    transition: background 120ms, border-color 120ms, color 120ms;
}

.diagram-card:hover .diagram-card__delete {
    display: flex;
}

@media (hover: none) {
    .diagram-card__delete {
        display: flex;
    }
}

.diagram-card__delete:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.35);
    color: #ef4444;
}

.diagram-card__duplicate {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 26px;
    height: 26px;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 4px;
    color: var(--text-muted);
    cursor: pointer;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: 50%;
    transition: background 120ms, border-color 120ms, color 120ms;
}

.diagram-card:hover .diagram-card__duplicate {
    display: flex;
}

.diagram-card__duplicate:hover {
    color: var(--color-primary-text);
    border-color: var(--color-primary);
    background: rgba(61, 122, 92, 0.16);
}

.diagram-card__visibility {
    position: absolute;
    top: 8px;
    left: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    color: var(--text-secondary);
    background: rgba(0, 0, 0, 0.48);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 999px;
    backdrop-filter: blur(4px);
    pointer-events: none;
    z-index: 2;
}

.diagram-card__visibility--public {
    color: var(--color-primary);
    border-color: color-mix(in srgb, var(--color-primary) 50%, rgba(255, 255, 255, 0.12));
    background: color-mix(in srgb, var(--color-primary) 18%, rgba(0, 0, 0, 0.54));
}

.diagram-card__visibility--shared {
    color: #93c5fd;
    border-color: color-mix(in srgb, #60a5fa 45%, rgba(255, 255, 255, 0.12));
    background: color-mix(in srgb, #60a5fa 16%, rgba(0, 0, 0, 0.54));
}

.diagram-card__badge {
    position: absolute;
    left: 8px;
    bottom: 8px;
    padding: 4px 7px;
    color: var(--text-primary);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    background: rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 999px;
    backdrop-filter: blur(4px);
}

/* ── Card footer ────────────────────────────────────────────── */
.diagram-card__footer {
    padding: 0.55rem 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    position: relative;
}

.diagram-card__name {
    font-size: 0.875rem;
    color: var(--text-secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex: 0 1 50%;
    text-align: center;
}

.diagram-card__rename {
    position: absolute;
    right: 0.65rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.25rem;
    border: 0;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    opacity: 0;
    transition: color 120ms, opacity 120ms;
}

.diagram-card:hover .diagram-card__rename,
.diagram-card__rename:focus-visible {
    opacity: 1;
}

.diagram-card__rename:hover {
    color: var(--text-primary);
}

.diagram-card__db-icon {
    position: absolute;
    left: 0.8rem;
    width: 32px;
    height: 32px;
    opacity: 0.85;
}

.diagram-card__name-input {
    flex: 0 1 35%;
    font-size: 0.875rem;
    border: none;
    border-bottom: 1px solid var(--color-primary);
    background: transparent;
    outline: none;
    color: var(--text-secondary);
    font-family: inherit;
    text-align: center;
    padding: 0;
    min-width: 0;
}

/* ── Create modal ───────────────────────────────────────────── */
.create-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 200;
}

.create-modal {
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    width: 400px;
    max-width: calc(100vw - 2rem);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

/* Header */
.create-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}

.create-modal__title {
    font-family: ui-monospace, monospace;
    font-size: 0.76rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-secondary);
}

.create-modal__close {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    background: none;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    color: var(--text-muted);
    transition: color 0.12s, background 0.12s;
}

.create-modal__close:hover {
    color: var(--text-secondary);
    background: var(--hover-bg);
}

/* Body */
.create-modal__body {
    padding: 20px 20px 0;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.create-modal__field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.create-modal__label {
    font-family: ui-monospace, monospace;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-muted);
}

.create-modal__input {
    width: 100%;
    padding: 9px 12px;
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-radius: 7px;
    font-size: 0.88rem;
    font-family: inherit;
    color: var(--text-primary);
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.12s;
}

.create-modal__input::placeholder {
    color: var(--text-muted);
}

.create-modal__input:focus {
    border-color: var(--color-primary-text);
}

/* DB chips */
.create-modal__db-options {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}

.db-option {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 10px 12px 8px;
    border: 1.5px solid var(--border-color);
    border-radius: 8px;
    background: transparent;
    cursor: pointer;
    transition: border-color 0.12s, background 0.12s;
}

.db-option img {
    width: 22px;
    height: 22px;
    object-fit: contain;
}

.db-option span {
    font-family: ui-monospace, monospace;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted);
    transition: color 0.12s;
}

.db-option:hover:not(.db-option--active) {
    border-color: var(--border-strong);
    background: var(--bg-surface-alt);
}

.db-option--active {
    border-color: var(--color-primary-text);
    background: rgba(93, 181, 131, 0.1);
}

.db-option--active span {
    color: var(--color-primary-text);
}

/* Visibility row */
.create-modal__vis-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.create-modal__vis-chips {
    display: flex;
    gap: 6px;
}

.create-modal__vis-btn {
    padding: 6px 14px;
    font-family: ui-monospace, monospace;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    border: 1.5px solid var(--border-color);
    border-radius: 6px;
    background: transparent;
    color: var(--text-muted);
    cursor: pointer;
    transition: border-color 0.12s, background 0.12s, color 0.12s;
    white-space: nowrap;
}

.create-modal__vis-btn:hover:not(.create-modal__vis-btn--active) {
    border-color: var(--border-strong);
    background: var(--bg-surface-alt);
    color: var(--text-subtle);
}

.create-modal__vis-btn--active {
    border-color: var(--color-primary-text);
    background: rgba(93, 181, 131, 0.1);
    color: var(--color-primary-text);
}

/* Library checkbox */
.create-modal__checkbox-label {
    display: flex;
    align-items: center;
    gap: 7px;
    font-family: ui-monospace, monospace;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-muted);
    cursor: pointer;
    white-space: nowrap;
}

.create-modal__checkbox {
    accent-color: var(--color-primary-text);
    width: 15px;
    height: 15px;
    cursor: pointer;
    flex-shrink: 0;
}

/* Help icon + tooltip */
.create-modal__help-icon {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 1px solid var(--border-color);
    font-size: 0.65rem;
    color: var(--text-muted);
    cursor: default;
    flex-shrink: 0;
}

.create-modal__help-icon:hover .create-modal__tooltip,
.create-modal__tooltip:hover {
    opacity: 1;
    pointer-events: auto;
}

.create-modal__tooltip {
    position: absolute;
    bottom: calc(100% + 6px);
    right: 0;
    width: 220px;
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-radius: 6px;
    padding: 0.5rem 0.65rem;
    font-size: 0.72rem;
    font-family: inherit;
    color: var(--text-subtle);
    line-height: 1.45;
    text-transform: none;
    letter-spacing: 0;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.15s;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Footer */
.create-modal__footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    margin-top: 20px;
    border-top: 1px solid var(--border-color);
}

.create-modal__btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 14px;
    border-radius: 7px;
    font-family: ui-monospace, monospace;
    font-size: 0.78rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s;
    white-space: nowrap;
}

.create-modal__btn--cancel {
    border: 1px solid var(--border-color);
    background: var(--bg-surface-alt);
    color: var(--text-secondary);
}

.create-modal__btn--cancel:hover {
    border-color: var(--border-strong);
    background: var(--hover-bg-alt);
}

.create-modal__btn--create {
    border: none;
    background: var(--color-primary-text);
    color: #0c1f15;
}

.create-modal__btn--create:hover {
    background: #6ec994;
}
</style>
