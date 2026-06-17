<template>
    <BaseEdge :id="id" :style="edgeStyle" :path="edgePath" />
</template>

<script setup>
import { BaseEdge, getSmoothStepPath } from '@vue-flow/core'
import { computed } from 'vue'

defineOptions({ inheritAttrs: false })

const props = defineProps({
    id: String,
    sourceX: Number,
    sourceY: Number,
    targetX: Number,
    targetY: Number,
    sourcePosition: String,
    targetPosition: String,
    style: Object,
})

const edgePath = computed(() => getSmoothStepPath(props)[0])
const edgeStyle = computed(() => ({
    fill: 'none',
    stroke: '#f59e0b',
    strokeWidth: 2,
    strokeDasharray: '4 4',
    ...props.style,
}))
</script>
