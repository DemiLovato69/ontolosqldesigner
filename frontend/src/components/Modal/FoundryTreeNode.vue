<template>
    <div class="fb-tnode">
        <div
            class="fb-trow"
            :class="{ 'is-selected': node.id === selectedId }"
            :style="{ paddingLeft: (6 + depth * 14) + 'px' }"
            @click="$emit('select', node)"
        >
            <button
                v-if="node.expandable"
                type="button"
                class="fb-tcaret"
                @click.stop="$emit('toggle', node)"
            >
                <SvgIcon :name="node.expanded ? 'chevron-down' : 'chevron-right'" :size="12" />
            </button>
            <span v-else class="fb-tcaret fb-tcaret--leaf"></span>

            <span class="fb-ticon" :class="`fb-ticon--${node.colorKind}`">
                <SvgIcon :name="node.icon" :size="14" />
            </span>
            <span class="fb-tname">{{ node.name }}</span>
            <span v-if="node.loading" class="fb-tspin">…</span>
        </div>

        <div v-if="node.expanded && node.children && node.children.length" class="fb-tchildren">
            <FoundryTreeNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :selected-id="selectedId"
                :depth="depth + 1"
                @select="$emit('select', $event)"
                @toggle="$emit('toggle', $event)"
            />
        </div>
    </div>
</template>

<script setup>
import SvgIcon from '../SvgIcon.vue'

defineProps({
    node: { type: Object, required: true },
    selectedId: { type: [String, Number], default: null },
    depth: { type: Number, default: 0 },
})
defineEmits(['select', 'toggle'])
</script>

<style scoped>
.fb-trow {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 4px 8px 4px 0;
    cursor: pointer;
    border-radius: 5px;
    color: var(--text-primary);
    font-size: 0.8rem;
}

.fb-trow:hover {
    background: var(--hover-bg-alt);
}

.fb-trow.is-selected {
    background: var(--hover-bg);
    color: var(--text-primary);
}

.fb-tcaret {
    flex-shrink: 0;
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 0;
    background: none;
    color: var(--text-muted);
    cursor: pointer;
}

.fb-tcaret--leaf {
    cursor: default;
}

.fb-ticon {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    color: var(--text-muted);
}

.fb-ticon--space { color: #5b8def; }
.fb-ticon--folder { color: #a78bfa; }
.fb-ticon--dataset { color: #5db583; }
.fb-ticon--file { color: #9aa0a6; }

.fb-tname {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.fb-tspin {
    margin-left: auto;
    color: var(--text-muted);
    font-size: 0.7rem;
}
</style>
