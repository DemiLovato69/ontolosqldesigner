<template>
  <div class="header">

    <div class="buttons">
        <button class="btn btn-primary" @click="openExportModal">Export</button>
        <button class="btn btn-primary" @click="openImportModal">Import</button>
        <button class="btn btn-primary" @click="addTable">Add Table</button>
    </div>

    <div class="buttons">
        <button class="btn btn-primary" @click="openDiagramsModal">Diagrams</button>

        <div v-if="loggedIn">
            <button class="btn btn-primary" @click="logout()">Log out</button>
        </div>

        <div v-else>
            <register-component></register-component>
            <login-component></login-component>
        </div>

    </div>

  </div>

</template>

<script setup>
import {Auth} from "../services/Auth";
import {computed} from "vue";
import { useStore } from 'vuex';

const store = useStore();


const loggedIn = computed(() => store.getters.loggedIn);
const logout = () => {
    Auth.logout(store);
}
const {
    addTable,
    openExportModal,
    openImportModal,
    openDiagramsModal
}
= defineProps([
    'addTable',
    'openExportModal',
    'openImportModal',
    'openDiagramsModal'
]);
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1rem;
  background-color: #007BFF;
  color: white;
}
.buttons,
.auth-buttons {
  display: flex;
  align-items: center;
}

.buttons button,
.auth-buttons button {
  margin-left: 1rem;
}
</style>
