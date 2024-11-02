<template>
  <div class="header">

      <div class="buttons">
          <router-link to="/">Home</router-link>
          <button class="btn btn-primary" @click="openExportModal">Export</button>
          <button class="btn btn-primary" @click="openImportModal">Import</button>
          <button class="btn btn-primary" @click="addTable">Add Table</button>
      </div>

      <button class="btn btn-primary" @click="saveDiagram">Save</button>
      <button class="btn btn-primary" @click="logout">Log out</button>
  </div>

</template>

<script setup>
import {Auth} from "../services/Auth";
import {computed} from "vue";
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

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1rem;
  background-color: #007BFF;
  color: white;
}
</style>
