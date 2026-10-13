<script setup lang="ts">
  import { ref, computed, onMounted, onUnmounted } from "vue";
  import { RouterLink, RouterView, useRouter } from "vue-router";
  import {
    Squares2X2Icon,
    CubeIcon,
    ChatBubbleLeftRightIcon,
    DocumentChartBarIcon,
    ArrowRightEndOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
  } from "@heroicons/vue/16/solid";
  import BrandLogo from "./BrandLogo.vue";
  import { currentUser, post } from "../lib/api";
  import { roleLabels } from "../lib/presentation";
  const open = ref(false);
  const narrow = ref(false);
  let media: MediaQueryList;
  function syncWidth() {
    narrow.value = media.matches;
    if (!narrow.value) open.value = false;
  }
  function onEscape(e: KeyboardEvent) {
    if (e.key === "Escape" && open.value) {
      open.value = false;
      document.querySelector<HTMLButtonElement>(".menu-toggle")?.focus();
    }
  }
  onMounted(() => {
    media = window.matchMedia("(max-width: 1000px)");
    syncWidth();
    media.addEventListener("change", syncWidth);
    document.addEventListener("keydown", onEscape);
  });
  onUnmounted(() => {
    media?.removeEventListener("change", syncWidth);
    document.removeEventListener("keydown", onEscape);
  });
  const error = ref("");
  const router = useRouter();
  const links = computed(() => [
    {
      to: "/",
      name:
        currentUser.value?.role === "admin"
          ? "Ringkasan"
          : currentUser.value?.role === "courier"
            ? "Tugas hari ini"
            : "Paket saya",
      icon: Squares2X2Icon,
    },
    {
      to: "/kiriman",
      name: currentUser.value?.role === "courier" ? "Daftar tugas" : "Pengiriman",
      icon: CubeIcon,
    },
    { to: "/pesan", name: "Notifikasi", icon: ChatBubbleLeftRightIcon },
    ...(currentUser.value?.role === "admin"
      ? [
          {
            to: "/laporan",
            name: "Laporan & riwayat",
            icon: DocumentChartBarIcon,
          },
        ]
      : []),
  ]);
  async function logout() {
    try {
      await post("logout", {});
      currentUser.value = null;
      await router.push("/masuk");
    } catch (e) {
      error.value = (e as Error).message;
    }
  }
</script>
<template>
  <div class="app-shell">
    <a class="skip-link" href="#main-content">Langsung ke isi halaman</a>
    <aside
      id="main-navigation"
      class="sidebar"
      :class="{ 'is-open': open }"
      :inert="narrow && !open"
    >
      <RouterLink
        to="/"
        class="brand-link"
        aria-label="Beranda Anteraja Pharma"
        @click="open = false"
      >
        <BrandLogo />
      </RouterLink>
      <p class="sidebar-label">
        {{ currentUser ? roleLabels[currentUser.role] : "Ruang kerja" }}
      </p>
      <nav aria-label="Navigasi utama">
        <RouterLink
          v-for="item in links"
          :key="item.to"
          :to="item.to"
          :exact-active-class="'active'"
          @click="open = false"
        >
          <component :is="item.icon" aria-hidden="true" />
          {{ item.name }}
        </RouterLink>
      </nav>
      <section class="sidebar-note">
        <p class="weight">Penanganan yang tercatat.</p>
        <p class="small">Dari pemeriksaan pertama sampai paket diterima.</p>
      </section>
      <footer class="sidebar-user">
        <span class="avatar" aria-hidden="true">{{ currentUser?.name.charAt(0) }}</span>
        <p>
          <strong>{{ currentUser?.name }}</strong>
          <span>{{ currentUser ? roleLabels[currentUser.role] : "" }}</span>
        </p>
        <button type="button" aria-label="Keluar dari akun" class="icon-button" @click="logout">
          <ArrowRightEndOnRectangleIcon />
        </button>
      </footer>
    </aside>
    <div class="workspace">
      <header class="topbar">
        <button
          type="button"
          class="icon-button menu-toggle"
          :aria-expanded="open"
          aria-controls="main-navigation"
          aria-label="Buka atau tutup menu"
          @click="open = !open"
        >
          <component :is="open ? XMarkIcon : Bars3Icon" />
        </button>
        <RouterLink to="/" class="mobile-brand" aria-label="Beranda Anteraja Pharma">
          <BrandLogo />
        </RouterLink>
        <p class="desktop-context">
          Pengiriman kesehatan
          <span class="topbar-divider">/</span>
          <strong>Pharma</strong>
        </p>
        <p class="small topbar-context">
          <span class="live-dot" />
          Prototype · data contoh
        </p>
      </header>
      <p v-if="error" class="notice danger" role="alert">{{ error }}</p>
      <main id="main-content" tabindex="-1" class="main-content">
        <RouterView />
      </main>
      <footer class="app-footer">
        <p>Anteraja Pharma · Prototype hackathon</p>
        <p>Periksa dulu. Putuskan dengan alasan.</p>
      </footer>
    </div>
  </div>
</template>
