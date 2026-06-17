<template>
    <header class="header">
        <div class="flex-items">
            <a href="/diagrams">
                <img src="../icons/logo.svg" alt="ontolo-sql-designer" class="logo">
            </a>
        </div>
        <div v-if="diagramTitle || diagramSharingStatus" class="header-center-status" :title="diagramSharingStatus?.title || diagramTitle" :aria-label="diagramSharingStatus?.title || diagramTitle">
            <span v-if="diagramTitle" class="header-diagram-title">{{ diagramTitle }}</span>
            <span v-if="diagramSharingStatus" class="header-share-status" :class="`header-share-status--${diagramSharingStatus.kind}`">
                <SvgIcon :name="diagramSharingStatus.icon" :size="15" />
            </span>
        </div>
        <div class="flex-items">
            <button v-if="!store.getters.isAuthenticated && route.name === 'demo'" class="hbtn-cta" @click="router.push({ name: 'login' })">Sign in</button>
            <template v-if="appHeaderActions.diagram">
                <button v-if="isVisible(appHeaderActions.diagram.import)" class="hbtn" @click="appHeaderActions.diagram.import.run" title="Import schema">
                    <SvgIcon name="import" :size="17" />
                </button>
                <button v-if="isVisible(appHeaderActions.diagram.export)" class="hbtn" @click="appHeaderActions.diagram.export.run" title="Export">
                    <SvgIcon name="export" :size="17" />
                </button>
                <div v-if="isVisible(appHeaderActions.diagram.save)" class="header-save-wrap">
                    <button class="hbtn" @click="appHeaderActions.diagram.save.run" title="Save (Ctrl/⌘+S)" :disabled="isDisabled(appHeaderActions.diagram.save)">
                        <SvgIcon name="save" :size="17" />
                    </button>
                    <span v-if="!isDiagramDemo()" class="header-save-dot" :class="{ 'header-save-dot--saved': isDiagramSaved() }"></span>
                </div>
            </template>
            <button v-if="store.getters.isAuthenticated" class="hbtn" @click="router.push({ name: 'diagrams' })" title="View diagrams">
                <SvgIcon name="eye" :size="17" />
            </button>
            <button v-if="store.getters.isAuthenticated" class="hbtn" @click="Auth.logout()" title="Log out">
                <SvgIcon name="logout" :size="17" />
            </button>
        </div>
    </header>
</template>

<script setup>
import { computed, onMounted, unref } from 'vue'
import { Auth } from '@/services/Auth.js'
import SvgIcon from './SvgIcon.vue'
import { useStore } from 'vuex'
import { useRoute } from 'vue-router'
import router from '@/router/index.js'
import { appHeaderActions } from '@/composables/useAppHeaderActions.js'
import '@/css/header.css'

const store = useStore()
const route = useRoute()

const isVisible = (action) => action && unref(action.visible) !== false
const isDisabled = (action) => unref(action?.disabled) === true
const isDiagramDemo = () => unref(appHeaderActions.diagram?.isDemo) === true
const isDiagramSaved = () => unref(appHeaderActions.diagram?.isSaved) === true
const diagramTitle = computed(() => unref(appHeaderActions.diagram?.diagramName) || '')
const diagramSharingStatus = computed(() => unref(appHeaderActions.diagram?.sharingStatus) ?? null)


onMounted(() => {
    if (document.querySelector('script[src*="googletagmanager"]')) return

    const gtagScript = document.createElement('script')
    gtagScript.async = true
    gtagScript.src = 'https://www.googletagmanager.com/gtag/js?id=G-4L116MPX4C'
    document.head.appendChild(gtagScript)

    window.dataLayer = window.dataLayer || []
    function gtag() { window.dataLayer.push(arguments) }
    window.gtag = gtag
    gtag('js', new Date())
    gtag('config', 'G-4L116MPX4C')
})
</script>

<style scoped>
.logo {
    margin-top: 4px;
}
</style>
