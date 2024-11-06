<template>
    <header class="header">
        <div class="button-group">
            <button class="btn btn-secondary"><a href="/">Home</a></button>
            <button class="btn btn-secondary" @click="openExportModal">Export</button>
            <button class="btn btn-secondary" @click="openImportModal">Import</button>
            <button class="btn btn-secondary" @click="addTable">Add Table</button>
        </div>
        <div class="button-group">
            <button class="btn btn-secondary" @click="saveDiagram">Save</button>
            <button class="btn btn-secondary" @click="logout">Log out</button>
        </div>
    </header>
</template>

<script setup>
import { Auth } from "../services/Auth";
import { useStore } from 'vuex';

const store = useStore();

const logout = () => {
    Auth.logout(store);
}

const {
    addTable,
    openExportModal,
    openImportModal,
    saveDiagram
}
= defineProps([
    'addTable',
    'openExportModal',
    'openImportModal',
    'saveDiagram'
]);
</script>
