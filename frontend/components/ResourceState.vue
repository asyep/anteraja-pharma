<script setup lang="ts">
  defineProps<{ loading: boolean; error: string; label?: string }>();
  defineEmits<{ retry: [] }>();
</script>
<template>
  <section v-if="error" class="notice danger" role="alert">
    <p>{{ error }}</p>
    <button type="button" class="button secondary" @click="$emit('retry')">Coba lagi</button>
  </section>
  <section v-else-if="loading" class="loading-state" role="status" aria-live="polite">
    <p>{{ label || "Memuat data kiriman…" }}</p>
    <div class="skeleton" />
    <div class="skeleton" />
    <div class="skeleton short" />
  </section>
</template>
