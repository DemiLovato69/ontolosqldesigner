<template>
  <div class="header">

    <div class="buttons">
        <button class="btn btn-primary" @click="openExportModal">Export</button>
        <button class="btn btn-primary" @click="openImportModal">Import</button>
        <button class="btn btn-primary" @click="addTable">Add Table</button>
    </div>

    <div class="auth-buttons">
        <register-component></register-component>
        <button class="btn btn-primary" @click="importData">Login</button>
    </div>

  </div>

</template>

<script setup>
const { addTable, openExportModal, openImportModal} = defineProps(['addTable', 'openExportModal', 'openImportModal']);
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
