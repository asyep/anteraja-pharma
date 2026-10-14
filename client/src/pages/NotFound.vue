<script setup lang="ts">
  import { RouterLink } from "vue-router";
</script>
<template>
  <section class="empty-state">
    <p class="overline">404 · Halaman tidak ditemukan</p>
    <h1>Sepertinya alamatnya berbeda.</h1>
    <p>Gunakan menu pengiriman untuk kembali ke paket dan tugasmu.</p>
    <RouterLink to="/" class="button primary">Kembali ke ringkasan</RouterLink>
  </section>
</template>
