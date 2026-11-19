<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";
import { RouterLink, RouterView, useRouter, useRoute } from "vue-router";
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
import "../customer.css";

const open = ref(false);
const narrow = ref(false);
const profileOpen = ref(false);
const router = useRouter();
const route = useRoute();
const error = ref("");

let media: MediaQueryList;
function syncWidth() {
  narrow.value = media.matches;
  if (!narrow.value) open.value = false;
}
function onEscape(e: KeyboardEvent) {
  if (e.key === "Escape") {
    if (open.value) {
      open.value = false;
      document.querySelector<HTMLButtonElement>(".menu-toggle")?.focus();
    }
    if (profileOpen.value) {
      profileOpen.value = false;
    }
  }
}

onMounted(() => {
  media = window.matchMedia("(max-width: 1000px)");
  syncWidth();
  media.addEventListener("change", syncWidth);
  document.addEventListener("keydown", onEscape);
  document.addEventListener("click", onDocClick);
});

onUnmounted(() => {
  media?.removeEventListener("change", syncWidth);
  document.removeEventListener("keydown", onEscape);
  document.removeEventListener("click", onDocClick);
});

function onDocClick(e: MouseEvent) {
  const target = e.target as HTMLElement | null;
  if (!target?.closest(".customer-avatar-wrap")) {
    profileOpen.value = false;
  }
}

const isBuyer = computed(() => currentUser.value?.role === "buyer");

const links = computed(() => [
  {
    to: "/",
    name:
      currentUser.value?.role === "admin"
        ? "Ringkasan"
        : currentUser.value?.role === "checker"
          ? "Pemeriksaan"
          : currentUser.value?.role === "courier"
            ? "Tugas hari ini"
            : "Paket saya",
    icon: Squares2X2Icon,
  },
  {
    to: "/kiriman",
    name:
      currentUser.value?.role === "checker"
        ? "Antrean pemeriksaan"
        : currentUser.value?.role === "courier"
          ? "Daftar tugas"
          : "Pengiriman",
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
  <!-- VENDOR / CUSTOMER LAYOUT (Top Navbar + 2 Nav Links) -->
  <div v-if="isBuyer" class="customer-shell">
    <header class="customer-header">
      <div class="customer-header-inner">
        <!-- Brand Logo -->
        <RouterLink to="/" class="customer-brand-link" aria-label="Beranda Anteraja Pharma">
          <BrandLogo />
        </RouterLink>

        <!-- Center 2 Navigation Pills: Beranda & Paket Saya -->
        <nav class="customer-nav-pills" aria-label="Navigasi Pelanggan">
          <RouterLink
            to="/"
            class="customer-nav-pill"
            :class="{ active: route.path === '/' }"
          >
            Beranda
          </RouterLink>
          <RouterLink
            to="/kiriman"
            class="customer-nav-pill"
            :class="{ active: route.path.startsWith('/kiriman') }"
          >
            Paket Saya
          </RouterLink>
        </nav>

        <!-- Header Right: Facility Badge, Notifications, User Profile -->
        <div class="customer-header-right">
          <div class="customer-facility-badge">
            <span class="material-symbols-outlined" style="font-size: 16px; color: var(--ap-secondary);">verified_user</span>
            <span>{{ currentUser?.name || 'RS Medika Sehat Jakarta' }} • Instalasi Farmasi Pusat</span>
          </div>

          <RouterLink
            to="/pesan"
            class="customer-icon-btn"
            aria-label="Pemberitahuan &amp; Notifikasi"
            title="Notifikasi"
          >
            <span class="material-symbols-outlined" style="font-size: 20px;">notifications</span>
            <span class="customer-unread-dot"></span>
          </RouterLink>

          <!-- User Avatar Dropdown -->
          <div class="customer-avatar-wrap">
            <button
              type="button"
              class="customer-avatar-btn"
              aria-label="Menu Pengguna"
              :aria-expanded="profileOpen"
              @click="profileOpen = !profileOpen"
            >
              <img
                src="https://lh3.googleusercontent.com/aida/AEtjO1WD-gOCrCse8erzmiQcEyWyhtlBjSeRxdsqRyUOQnqitxW7D9LyPnyFIjebpSyv8qfBcyb8u34SYmuK9ErYPNJn_FtSaKR4Ufz8CRG2WgbQhDjZKl-c33iLWN_bFN6b5eKM4V2tsJvdDfY3-HM-F_Qql-lr6DcmE8tZJqdnermsY-CsYz5_freCsLdUUtQxq5mCYGsIqPsjxzgpwng6AEfkxRQO4yrGPjZc-oEChyhu8yCXfHgUJT1dvEYWJ8CU124XIcD8b9-zb5Q"
                alt="Profile"
                class="customer-avatar-img"
                @error="($event.target as HTMLElement).style.display = 'none'"
              />
              <span class="customer-avatar-initials" style="display: none;">
                {{ currentUser?.name.charAt(0) || 'V' }}
              </span>
            </button>

            <!-- Dropdown Menu -->
            <div v-if="profileOpen" class="customer-dropdown">
              <div class="customer-dropdown-header">
                <div class="customer-dropdown-name">{{ currentUser?.name }}</div>
                <div class="customer-dropdown-role">Vendor / Pelanggan Farmasi</div>
              </div>
              <RouterLink
                to="/pesan"
                class="customer-dropdown-btn"
                @click="profileOpen = false"
              >
                <span class="material-symbols-outlined" style="font-size: 18px;">chat</span>
                <span>Pusat Notifikasi</span>
              </RouterLink>
              <button
                type="button"
                class="customer-dropdown-btn"
                style="color: var(--ap-primary); border-top: 1px solid var(--ap-surface-low); margin-top: 0.25rem; padding-top: 0.5rem;"
                @click="logout"
              >
                <span class="material-symbols-outlined" style="font-size: 18px;">logout</span>
                <span>Keluar Akun</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main id="main-content" tabindex="-1">
      <RouterView />
    </main>

    <!-- Customer Footer -->
    <footer class="customer-footer">
      <div class="customer-footer-links">
        <a href="#sop" class="customer-footer-link">Panduan CDOB &amp; SOP Rantai Dingin</a>
        <span style="opacity: 0.4;">•</span>
        <a href="#hotline" class="customer-footer-link">Bantuan Farmasi 24 Jam</a>
        <span style="opacity: 0.4;">•</span>
        <a href="#privacy" class="customer-footer-link">Kebijakan Privasi &amp; Data Rekam Medis</a>
      </div>
      <div>
        <div class="customer-footer-cert">
          <span class="material-symbols-outlined" style="font-size: 16px; color: var(--ap-secondary);">health_and_safety</span>
          <span>Sertifikasi CDOB BPOM RI • WHO TRS 961 • ISO 9001:2015</span>
        </div>
      </div>
      <p class="customer-footer-copy">
        © 2026 PT Tri Adi Bersama (Anteraja Pharma). Layanan Pengiriman Rantai Dingin Farmasi &amp; Biologis Terpercaya.
      </p>
    </footer>
  </div>

  <!-- OPERATIONAL LAYOUT FOR ADMIN, CHECKER, COURIER -->
  <div v-else class="app-shell">
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
