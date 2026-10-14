<script setup lang="ts">
  import { RouterView } from "vue-router";
  import { ref, onErrorCaptured } from "vue";
  const failed = ref(false);
  onErrorCaptured(() => {
    failed.value = true;
    return false;
  });
  function reload() {
    window.location.reload();
  }
</script>
<template>
  <main v-if="failed" id="main-content" class="empty-state">
    <h1>Halaman belum bisa ditampilkan.</h1>
    <p>Muat ulang untuk mencoba kembali. Pembaruan yang sudah tersimpan tetap ada.</p>
    <button type="button" class="button primary" @click="reload">Muat ulang halaman</button>
  </main>
  <RouterView v-else />
</template>
