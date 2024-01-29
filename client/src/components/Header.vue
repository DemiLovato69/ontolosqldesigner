<template>
  <div class="header">

    <div class="buttons">
      <button @click="openExportModal">Export</button>
      <button @click="openImportModal">Import</button>
      <button @click="addTable">Add Table</button>
    </div>

    <div class="auth-buttons">
      <button @click="exportData">Register</button>
      <button @click="importData">Login</button>
    </div>
  </div>

</template>

<script setup>
import { defineProps } from 'vue';

const { addTable, openExportModal, openImportModal} = defineProps(['addTable', 'openExportModal', 'openImportModal']);

const exportData = () => {
  console.log('Export data logic');
};

const importData = () => {
  console.log('Import data logic');
};
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
  padding: 0.5rem 1rem;
  background-color: #fff;
  color: #007BFF;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}
</style>
