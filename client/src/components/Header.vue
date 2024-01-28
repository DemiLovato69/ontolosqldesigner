<template>

  <div class="header">
    <div class="logo">Your App Name</div>
    <div class="buttons">
      <button @click="exportData">Export</button>
      <button @click="importData">Import</button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const exportData = () => {
  // Implement your export logic here
  console.log('Export data logic');
};

const importData = () => {
  // Implement your import logic here
  console.log('Import data logic');
};
</script>

<style scoped>
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: #007BFF;
  color: white;
}

.logo {
  font-size: 1.5rem;
}

.buttons button {
  margin-left: 1rem;
  padding: 0.5rem 1rem;
  background-color: #fff;
  color: #007BFF;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}
</style>
