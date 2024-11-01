<template>
  <div class="header">

      <div class="buttons">
          <a href="/">Home</a>
        <button class="btn btn-primary" @click="openExportModal">Export</button>
        <button class="btn btn-primary" @click="openImportModal">Import</button>
        <button class="btn btn-primary" @click="addTable">Add Table</button>
      </div>

      <h1 class="marginless">{{currentDiagramName}}</h1>

      <button class="btn btn-primary" @click="saveDiagram">Save</button>
      <button class="btn btn-primary" @click="logout">Log out</button>
  </div>

</template>

<script setup>
import {Auth} from "../services/Auth";
import {computed} from "vue";
import { useStore } from 'vuex';

const store = useStore();

const currentDiagramName = computed(() => store.state.current_diagram_name);

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
