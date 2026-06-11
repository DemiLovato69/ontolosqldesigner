<template>
    <div class="modal-overlay" @click.self="$emit('close')">
        <section
            class="hotkeys-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="hotkeys-title"
        >
            <header class="hotkeys-header">
                <div>
                    <h2 id="hotkeys-title">Keyboard Shortcuts</h2>
                    <p>Diagram and field editing shortcuts.</p>
                </div>
                <button
                    class="close-button"
                    type="button"
                    aria-label="Close keyboard shortcuts"
                    @click="$emit('close')"
                >
                    <SvgIcon name="close" :size="20" />
                </button>
            </header>

            <div class="hotkeys-content">
                <section
                    v-for="group in shortcutGroups"
                    :key="group.title"
                    class="shortcut-group"
                >
                    <h3>{{ group.title }}</h3>
                    <div
                        v-for="shortcut in group.shortcuts"
                        :key="shortcut.description"
                        class="shortcut-row"
                    >
                        <span>{{ shortcut.description }}</span>
                        <span class="shortcut-keys">
                            <kbd v-for="key in shortcut.keys" :key="key">
                                {{ key }}
                            </kbd>
                        </span>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'
import SvgIcon from '../SvgIcon.vue'

const emit = defineEmits(['close'])

const shortcutGroups = [
    {
        title: 'Diagram',
        shortcuts: [
            { keys: ['Ctrl / ⌘', 'Shift', 'A'], description: 'Add a table' },
            { keys: ['Ctrl / ⌘', 'D'], description: 'Duplicate the active table' },
            { keys: ['Ctrl / ⌘', 'S'], description: 'Save the diagram' },
            { keys: ['Ctrl / ⌘', 'Z'], description: 'Undo' },
            { keys: ['Ctrl / ⌘', 'Y'], description: 'Redo' },
            { keys: ['Esc'], description: 'Cancel table placement' },
        ],
    },
    {
        title: 'Editing',
        shortcuts: [
            { keys: ['Shift', 'Enter'], description: 'Add a row below the current row' },
            { keys: ['Shift', 'Tab'], description: 'Move to the previous row' },
            { keys: ['Tab'], description: 'Move to the next row' },
            { keys: ['Ctrl / ⌘', 'Enter'], description: 'Save a note or description' },
            { keys: ['Enter / Tab'], description: 'Add an enum value' },
        ],
    },
]

const handleKeydown = (event) => {
    if (event.key === 'Escape') emit('close')
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(0, 0, 0, 0.58);
    backdrop-filter: blur(2px);
}

.hotkeys-modal {
    width: min(560px, 100%);
    max-height: min(720px, calc(100vh - 48px));
    overflow: hidden;
    color: var(--text-primary);
    background: var(--bg-surface);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
}

.hotkeys-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 22px 24px;
    border-bottom: 1px solid var(--border-color);
}

.hotkeys-header h2 {
    margin: 0;
    font-size: 20px;
}

.hotkeys-header p {
    margin: 5px 0 0;
    color: var(--text-secondary);
    font-size: 13px;
}

.close-button {
    display: inline-flex;
    flex: 0 0 auto;
    align-items: center;
    justify-content: center;
    padding: 6px;
    color: var(--text-secondary);
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 6px;
}

.close-button:hover {
    color: var(--text-primary);
    background: var(--bg-surface-alt);
}

.hotkeys-content {
    max-height: calc(100vh - 180px);
    padding: 8px 24px 24px;
    overflow-y: auto;
}

.shortcut-group h3 {
    margin: 18px 0 8px;
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.shortcut-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    min-height: 45px;
    padding: 7px 0;
    color: var(--text-secondary);
    font-size: 14px;
    border-bottom: 1px solid var(--border-light);
}

.shortcut-keys {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 5px;
}

kbd {
    min-width: 28px;
    padding: 4px 7px;
    color: var(--text-primary);
    font-family: inherit;
    font-size: 12px;
    font-weight: 600;
    line-height: 1;
    text-align: center;
    background: var(--bg-surface-alt);
    border: 1px solid var(--border-color);
    border-bottom-width: 2px;
    border-radius: 5px;
}

@media (max-width: 520px) {
    .modal-overlay {
        padding: 12px;
    }

    .hotkeys-header,
    .hotkeys-content {
        padding-right: 16px;
        padding-left: 16px;
    }

    .shortcut-row {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }
}
</style>
