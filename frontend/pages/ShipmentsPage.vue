<script setup lang="ts">
  import { onMounted, ref } from "vue";
  import { RouterLink, useRoute } from "vue-router";
  import { MagnifyingGlassIcon, PlusIcon } from "@heroicons/vue/16/solid";
  import { currentUser } from "../lib/api";
  import { labels } from "../lib/presentation";
  import { useResource } from "../composables/useResource";
  import type { Shipment, Paginated } from "../types";
  import ShipmentTable from "../components/ShipmentTable.vue";
  import ResourceState from "../components/ResourceState.vue";
  const route = useRoute(),
    query = ref(""),
    status = ref(typeof route.query.status === "string" ? route.query.status : ""),
    perPage = ref(20);
  const { data, loading, error, load } = useResource<Paginated<Shipment>>();
  function search(page = 1) {
    const params = new URLSearchParams({
      q: query.value,
      status: status.value,
      per_page: String(perPage.value),
      page: String(page),
    });
    load("shipments?" + params);
  }
  onMounted(() => search());
</script>
<template>
  <section class="page-heading">
    <div>
      <p class="overline">
        {{ currentUser?.role === "courier" ? "Tugas Satria" : "Pengiriman Pharma" }}
      </p>
      <h1>
        {{ currentUser?.role === "buyer" ? "Semua paketmu." : "Setiap kiriman, terlihat jelas." }}
      </h1>
      <p class="muted">Cari resi, nama penerima, atau kota tujuan. Data mengikuti akses akunmu.</p>
    </div>
    <RouterLink v-if="currentUser?.role === 'admin'" to="/kiriman/baru" class="button primary">
      <PlusIcon />
      Buat kiriman
    </RouterLink>
  </section>
  <form class="filter-bar" @submit.prevent="search()">
    <div class="search-field">
      <label for="search">Cari kiriman</label>
      <div class="input-icon">
        <MagnifyingGlassIcon />
        <input
          id="search"
          v-model="query"
          name="q"
          maxlength="120"
          placeholder="Resi, penerima, atau kota"
          type="search"
        />
      </div>
    </div>
    <div>
      <label for="status">Tahap pengiriman</label>
      <select id="status" v-model="status" name="status" @change="search()">
        <option value="">Semua tahap</option>
        <option v-for="(label, key) in labels" :key="key" :value="key">
          {{ label }}
        </option>
      </select>
    </div>
    <div>
      <label for="per-page">Baris per halaman</label>
      <select id="per-page" v-model="perPage" name="per_page" @change="search()">
        <option :value="10">10 baris</option>
        <option :value="20">20 baris</option>
        <option :value="50">50 baris</option>
        <option :value="100">100 baris</option>
      </select>
    </div>
    <button type="submit" class="button secondary" :disabled="loading">Cari</button>
  </form>
  <ResourceState :loading="loading" :error="error" @retry="search()" />
  <section v-if="data && !loading">
    <header class="list-heading">
      <h2>{{ data.total }} kiriman ditemukan</h2>
      <p class="small muted">Hasil sesuai pencarian dan tahap yang dipilih</p>
    </header>
    <ShipmentTable v-if="data.data.length" :shipments="data.data" />
    <div v-else class="empty-state">
      <h3>Belum ada kiriman yang cocok.</h3>
      <p>Coba resi atau kota lain, atau hapus filter untuk melihat semua kiriman.</p>
      <button
        type="button"
        class="button secondary"
        @click="
          query = '';
          status = '';
          search();
        "
      >
        Hapus filter
      </button>
    </div>
    <nav v-if="data.last_page > 1" class="pagination" aria-label="Halaman daftar kiriman">
      <button
        type="button"
        class="button secondary"
        :disabled="data.current_page === 1"
        @click="search(data.current_page - 1)"
      >
        Sebelumnya
      </button>
      <p class="small">Halaman {{ data.current_page }} dari {{ data.last_page }}</p>
      <button
        type="button"
        class="button secondary"
        :disabled="data.current_page === data.last_page"
        @click="search(data.current_page + 1)"
      >
        Berikutnya
      </button>
    </nav>
  </section>
</template>
