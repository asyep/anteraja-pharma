<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { currentUser } from "../lib/api";
import { useResource } from "../composables/useResource";
import type { Shipment } from "../types";
import "../customer.css";

const router = useRouter();
const searchQuery = ref("");
const notice = ref("");
const activeModal = ref<"pickup" | "audit" | "hotline" | "validation" | "sop" | null>(null);

function notify(msg: string) {
  notice.value = msg;
  activeModal.value = null;
  setTimeout(() => {
    notice.value = "";
  }, 4500);
}

const { data, loading, load } = useResource<{
  totals: Record<string, number>;
  priority: Shipment[];
}>();

onMounted(() => {
  load("dashboard");
});

// Calculations for Vendor/Customer hook
const totalActive = computed(() => {
  if (!data.value?.totals) return 4;
  const t = data.value.totals;
  const count = (t.in_transit || 0) + (t.assigned || 0) + (t.accepted || 0) + (t.inspection || 0) + (t.pending_approval || 0) + (t.arrived || 0);
  return count > 0 ? count : 4;
});

const transitCount = computed(() => (data.value?.totals?.in_transit || 0) + (data.value?.totals?.accepted || 0) || 2);
const qcCount = computed(() => (data.value?.totals?.inspection || 0) + (data.value?.totals?.pending_approval || 0) || 1);
const nearCount = computed(() => (data.value?.totals?.arrived || 0) || 1);

const nearestShipment = computed(() => {
  if (data.value?.priority && data.value.priority.length > 0) {
    return data.value.priority[0];
  }
  return null;
});

function handleSearch() {
  if (!searchQuery.value.trim()) {
    router.push("/kiriman");
    return;
  }
  router.push({ path: "/kiriman", query: { q: searchQuery.value.trim() } });
}

function setQuery(term: string) {
  searchQuery.value = term;
  handleSearch();
}
</script>

<template>
  <div class="customer-viewport">
    <div
      v-if="notice"
      style="position: fixed; top: 5rem; right: 2rem; z-index: 999; background: #ffffff; color: var(--ap-on-surface); padding: 0.85rem 1.25rem; border-radius: 1rem; border: 1px solid var(--ap-secondary-container); box-shadow: var(--ap-shadow-lg); display: flex; align-items: center; gap: 0.65rem; font-size: 0.875rem; font-weight: 700; animation: fadeInDown 0.2s;"
    >
      <span class="material-symbols-outlined" style="color: var(--ap-secondary); font-size: 20px;">check_circle</span>
      <span>{{ notice }}</span>
    </div>
    <div class="customer-container">
      <!-- Context Breadcrumb Bar -->
      <div class="customer-context-bar">
        <div class="customer-breadcrumbs">
          <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: var(--ap-on-variant);">
            <span class="material-symbols-outlined" style="font-size: 16px; color: var(--ap-primary);">local_hospital</span>
            <span>Portal Farmasi &amp; Faskes</span>
          </span>
          <span style="opacity: 0.4;">/</span>
          <span style="font-weight: 700; color: var(--ap-on-surface);">Beranda Layanan</span>
        </div>
        <div class="customer-telemetry-badge">
          <span class="customer-pulse-dot"></span>
          <span>Sistem Telemetri Aktif</span>
        </div>
      </div>

      <!-- Hero & Primary Search Area -->
      <section class="customer-hero">
        <div class="customer-hero-glow-1"></div>
        <div class="customer-hero-glow-2"></div>

        <div class="customer-hero-content">
          <div class="customer-hero-badges">
            <span class="customer-badge-cert">
              <span class="material-symbols-outlined" style="font-size: 16px;">verified</span>
              Sertifikasi CDOB BPOM &amp; WHO TRS 961
            </span>
            <span class="customer-badge-temp">
              <span class="customer-pulse-dot"></span>
              Real-Time 2°C – 8°C Active
            </span>
          </div>

          <div style="display: flex; flex-direction: column; gap: 0.35rem;">
            <h1 class="customer-hero-title">
              Selamat Datang, <span>{{ currentUser?.name || 'Instalasi Farmasi RS Medika Sehat' }}</span>
            </h1>
            <p class="customer-hero-desc">
              Pantau kargo biologis, vaksin, dan obat rantai dingin secara presisi real-time.
            </p>
          </div>

          <div class="customer-search-box">
            <form class="customer-search-form" @submit.prevent="handleSearch">
              <div class="customer-search-icon">
                <span class="material-symbols-outlined" style="font-size: 22px;">sensors</span>
              </div>
              <input
                v-model="searchQuery"
                type="text"
                class="customer-search-input"
                placeholder="Lacak AWB farmasi (cth: #100098421088712 atau PHA-260001) atau nama sediaan obat..."
              />
              <button type="submit" class="customer-search-btn">
                <span>Lacak Kargo</span>
                <span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>
              </button>
            </form>

            <div class="customer-search-chips">
              <span style="font-weight: 700; color: var(--ap-on-surface); display: inline-flex; align-items: center; gap: 0.25rem;">
                <span class="material-symbols-outlined" style="font-size: 15px; color: var(--ap-outline);">history</span>
                Pencarian Terkini:
              </span>
              <button
                type="button"
                class="customer-chip-btn"
                @click="setQuery('#100098421088712')"
              >
                #100098421088712 <strong style="color: var(--ap-primary); font-weight: 700;">(Insulin)</strong>
              </button>
              <button
                type="button"
                class="customer-chip-btn"
                @click="setQuery('#100098421077321')"
              >
                #100098421077321 <strong style="color: var(--ap-secondary); font-weight: 700;">(Serum Anti-Bisa)</strong>
              </button>
            </div>
          </div>
        </div>
      </section>

      <!-- Ringkasan Operasional Hari Ini (3 Cards) -->
      <section style="margin-bottom: 2rem;">
        <div class="customer-section-header">
          <div>
            <h2 class="customer-section-title">Ringkasan Operasional Hari Ini</h2>
            <p class="customer-section-subtitle">Status kargo rantai dingin yang ditujukan ke fasilitas Anda</p>
          </div>
          <div class="customer-sync-badge">
            <span class="material-symbols-outlined" style="font-size: 14px;">sync</span>
            <span>Sinkronisasi otomatis (tiap 60d)</span>
          </div>
        </div>

        <div class="customer-summary-grid">
          <!-- Card 1: Paket Berjalan -->
          <div class="customer-card customer-card-pink">
            <div>
              <div class="customer-card-top">
                <span class="customer-card-label" style="color: var(--ap-primary);">Paket Berjalan</span>
                <div class="customer-icon-chip" style="background: var(--ap-primary);">
                  <span class="material-symbols-outlined" style="font-size: 20px;">local_shipping</span>
                </div>
              </div>
              <div class="customer-card-value-row">
                <span class="customer-big-number" style="color: var(--ap-primary);">{{ totalActive }}</span>
                <span class="customer-unit">Kiriman Aktif</span>
              </div>
              <div class="customer-chips-row">
                <span class="customer-mini-chip" style="background: #ffffff; color: var(--ap-primary); border: 1px solid rgba(177, 0, 69, 0.2);">
                  {{ transitCount }} Transit
                </span>
                <span class="customer-mini-chip" style="background: #ffffff; color: var(--ap-on-variant);">
                  {{ qcCount }} QC
                </span>
                <span class="customer-mini-chip" style="background: var(--ap-secondary-container); color: var(--ap-secondary-dark);">
                  {{ nearCount }} Dekat Faskes
                </span>
              </div>
            </div>
            <div class="customer-card-footer" style="border-top-color: rgba(177, 0, 69, 0.15);">
              <RouterLink to="/kiriman" class="customer-link-arrow">
                <span>Buka Paket Saya</span>
                <span class="material-symbols-outlined" style="font-size: 16px;">arrow_forward</span>
              </RouterLink>
              <span style="color: var(--ap-outline);">Terjadwal hari ini</span>
            </div>
          </div>

          <!-- Card 2: Integritas Cold-Chain -->
          <div class="customer-card customer-card-green">
            <div>
              <div class="customer-card-top">
                <span class="customer-card-label" style="color: var(--ap-secondary);">Integritas Cold-Chain</span>
                <div class="customer-icon-chip" style="background: var(--ap-secondary);">
                  <span class="material-symbols-outlined" style="font-size: 20px;">thermostat</span>
                </div>
              </div>
              <div class="customer-card-value-row">
                <span class="customer-big-number" style="color: var(--ap-secondary);">100%</span>
                <span class="customer-unit">CDOB Standar</span>
              </div>
              <div class="customer-bar-track">
                <div class="customer-bar-fill" style="width: 100%;"></div>
              </div>
              <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 600;">
                <span style="color: var(--ap-on-surface);">
                  Suhu Rata: <strong style="color: var(--ap-secondary);">4.2°C</strong>
                </span>
                <span style="color: var(--ap-secondary); font-weight: 700;">0 Insiden</span>
              </div>
            </div>
            <div class="customer-card-footer" style="border-top-color: rgba(0, 108, 74, 0.15);">
              <span style="display: inline-flex; align-items: center; gap: 0.35rem; color: var(--ap-secondary); font-weight: 700;">
                <span class="customer-pulse-dot"></span>
                ISO 17025 Aktif
              </span>
              <span style="color: var(--ap-outline); font-weight: 600;">Target 2–8°C</span>
            </div>
          </div>

          <!-- Card 3: Penerimaan Terdekat -->
          <div class="customer-card customer-card-blue">
            <div>
              <div class="customer-card-top">
                <span class="customer-card-label" style="color: var(--ap-on-surface);">Penerimaan Terdekat</span>
                <div class="customer-icon-chip" style="background: var(--ap-primary-container);">
                  <span class="material-symbols-outlined" style="font-size: 20px;">schedule</span>
                </div>
              </div>
              <div style="margin-top: 0.5rem;">
                <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                  <span style="font-size: 1.35rem; font-weight: 800; color: var(--ap-on-surface);">11:15 WIB</span>
                  <span style="font-size: 0.75rem; font-weight: 700; color: var(--ap-primary);">(~25 Mnt)</span>
                </div>
                <p style="font-size: 0.875rem; font-weight: 700; color: var(--ap-on-surface); margin: 0.2rem 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  {{ nearestShipment?.item_summary || 'Vaksin Flubio Monovalen' }}
                </p>
                <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: var(--ap-on-variant); margin-top: 0.25rem;">
                  <span class="material-symbols-outlined" style="font-size: 15px; color: var(--ap-secondary);">local_shipping</span>
                  <span>Reefer Satria Dimas (B 9281 PFA)</span>
                </div>
              </div>
            </div>
            <div class="customer-card-footer" style="border-top-color: var(--ap-surface-variant);">
              <span style="font-weight: 600; color: var(--ap-on-surface);">
                Suhu: <strong style="color: var(--ap-secondary);">3.8°C</strong>
              </span>
              <span class="customer-mini-chip" style="background: rgba(177, 0, 69, 0.1); color: var(--ap-primary);">
                Prioritas Dingin
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- Spotlight: Active Fleet Telemetry Map & Protocol -->
      <section class="customer-spotlight-grid">
        <!-- Left: Map Telemetry Radar -->
        <div class="customer-map-card">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
              <div style="width: 2.25rem; height: 2.25rem; border-radius: 50%; background: rgba(130, 245, 193, 0.3); display: flex; align-items: center; justify-content: center; color: var(--ap-secondary);">
                <span class="material-symbols-outlined" style="font-size: 18px;">near_me</span>
              </div>
              <div>
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--ap-on-surface); margin: 0;">Radar Armada Menuju Faskes Anda</h3>
                <p style="font-size: 0.75rem; color: var(--ap-on-variant); margin: 0.1rem 0 0;">Estimasi waktu tempuh diperbarui via GPS Reefer Tracker</p>
              </div>
            </div>
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--ap-secondary); background: rgba(130, 245, 193, 0.25); padding: 0.35rem 0.75rem; border-radius: 9999px;">
              3.4 KM Lagi
            </span>
          </div>

          <!-- Map Container -->
          <div
            class="customer-map-canvas"
            style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuDp0IBt8ANPGUVkUPbdqHu8QvPEVPNT1CLlUzfp6bDT4B1UnUgj9mWwjrsHpmQyHBZ_KpRzz_x4HK8YYr6YImlxGnO-kUBrceG-Y-9_l0BPv5JVSTmNLHLZwVJOssOlTWDCWxye0w0S-_P7w2bxwY44sfafDWd_IcmwL83reJsBVnIye6FvT94nVC9NiH1O1pYO9l535kgqHZday_f1cJNITZQqiZM17ys-hczHZk9gfc0Ku4kMGWEj4A');"
          >
            <!-- In-Transit HUD Card -->
            <div class="customer-hud-card">
              <div class="customer-hud-left">
                <div class="customer-hud-icon">
                  <span class="material-symbols-outlined" style="font-size: 22px;">vaccines</span>
                </div>
                <div>
                  <div class="customer-hud-batch">Batch #VAK-2024-098</div>
                  <div class="customer-hud-route">Jl. Gatot Subroto → {{ currentUser?.name || 'RS Medika Sehat Farmasi' }}</div>
                </div>
              </div>
              <div class="customer-hud-right">
                <div style="text-align: right;">
                  <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: var(--ap-on-variant);">Suhu Muatan</div>
                  <div style="font-size: 1.05rem; font-weight: 800; color: var(--ap-secondary); display: flex; align-items: center; justify-content: flex-end; gap: 0.35rem;">
                    <span style="width: 7px; height: 7px; border-radius: 50%; background: var(--ap-secondary);"></span>
                    4.1°C
                  </div>
                </div>
                <RouterLink
                  :to="nearestShipment ? '/kiriman/' + nearestShipment.id : '/kiriman'"
                  style="padding: 0.45rem 1rem; border-radius: 0.5rem; background: var(--ap-surface-low); color: var(--ap-on-surface); font-size: 0.8125rem; font-weight: 700; text-decoration: none;"
                >
                  Detail
                </RouterLink>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Protokol Serah Terima -->
        <div class="customer-protocol-card">
          <div>
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
              <span style="display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--ap-secondary);">
                <span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span>
                Kesiapan Penerimaan
              </span>
              <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; background: var(--ap-secondary-container); color: var(--ap-secondary-dark);">
                Cold-Room 2–8°C
              </span>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--ap-on-surface); margin: 0 0 0.35rem;">Protokol Serah Terima</h3>
            <p style="font-size: 0.8125rem; color: var(--ap-on-variant); margin: 0 0 0.75rem;">3 langkah wajib sebelum membuka segel tamper-evident kargo:</p>

            <div class="customer-steps-list">
              <div class="customer-step-item">
                <div class="customer-step-number">1</div>
                <div>
                  <div style="font-size: 0.875rem; font-weight: 700; color: var(--ap-on-surface);">Validasi Data Logger</div>
                  <div style="font-size: 0.75rem; color: var(--ap-on-variant);">Cetak &amp; simpan grafik suhu dari kurir</div>
                </div>
              </div>
              <div class="customer-step-item">
                <div class="customer-step-number">2</div>
                <div>
                  <div style="font-size: 0.875rem; font-weight: 700; color: var(--ap-on-surface);">Verifikasi Segel Fisik</div>
                  <div style="font-size: 0.75rem; color: var(--ap-on-variant);">Cocokkan nomor barcode dengan digital manifest</div>
                </div>
              </div>
              <div class="customer-step-item">
                <div class="customer-step-number">3</div>
                <div>
                  <div style="font-size: 0.875rem; font-weight: 700; color: var(--ap-on-surface);">Tanda Tangan APJ</div>
                  <div style="font-size: 0.75rem; color: var(--ap-on-variant);">Tanda tangan e-POD di terminal kurir</div>
                </div>
              </div>
            </div>
          </div>

          <div style="padding-top: 0.75rem; border-top: 1px solid rgba(0, 108, 74, 0.15); display: flex; align-items: center; justify-content: space-between;">
            <button
              type="button"
              style="background: transparent; border: none; padding: 0; font-size: 0.8125rem; font-weight: 700; color: var(--ap-primary); display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;"
              @click="activeModal = 'sop'"
            >
              <span>SOP Lengkap CDOB</span>
              <span class="material-symbols-outlined" style="font-size: 14px;">arrow_outward</span>
            </button>
            <span style="font-size: 0.6875rem; color: var(--ap-outline); font-weight: 600;">CDOB-2024.3</span>
          </div>
        </div>
      </section>

      <!-- Akses Cepat Layanan Faskes (4 Clean Minimalist Action Tiles) -->
      <section style="margin-bottom: 2rem;">
        <div style="margin-bottom: 1.25rem;">
          <h2 class="customer-section-title">Akses Cepat Layanan Faskes</h2>
          <p class="customer-section-subtitle">Navigasi langsung untuk operasional logistik medis harian</p>
        </div>

        <div class="customer-action-grid">
          <!-- Action 1: Semua Paket -->
          <RouterLink to="/kiriman" class="customer-action-tile" style="background: linear-gradient(180deg, rgba(255, 217, 222, 0.25) 0%, #ffffff 100%);">
            <div class="customer-tile-top">
              <div class="customer-tile-icon" style="background: var(--ap-primary);">
                <span class="material-symbols-outlined" style="font-size: 24px;">inventory_2</span>
              </div>
              <span class="material-symbols-outlined" style="color: var(--ap-outline);">arrow_forward</span>
            </div>
            <div>
              <h3 class="customer-tile-title">Semua Paket</h3>
              <span class="customer-tile-subtitle">Manifest &amp; Live Sensor</span>
            </div>
          </RouterLink>

          <!-- Action 2: Pick-Up Khusus -->
          <div
            class="customer-action-tile"
            style="background: linear-gradient(180deg, rgba(130, 245, 193, 0.25) 0%, #ffffff 100%);"
            @click="activeModal = 'pickup'"
          >
            <div class="customer-tile-top">
              <div class="customer-tile-icon" style="background: var(--ap-secondary);">
                <span class="material-symbols-outlined" style="font-size: 24px;">rv_hookup</span>
              </div>
              <span class="material-symbols-outlined" style="color: var(--ap-outline);">arrow_forward</span>
            </div>
            <div>
              <h3 class="customer-tile-title">Pick-Up Khusus</h3>
              <span class="customer-tile-subtitle">Reefer Box 2–8°C</span>
            </div>
          </div>

          <!-- Action 3: Laporan Audit -->
          <div
            class="customer-action-tile"
            style="background: linear-gradient(180deg, var(--ap-surface-low) 0%, #ffffff 100%);"
            @click="activeModal = 'audit'"
          >
            <div class="customer-tile-top">
              <div class="customer-tile-icon" style="background: #475569;">
                <span class="material-symbols-outlined" style="font-size: 24px;">description</span>
              </div>
              <span class="material-symbols-outlined" style="color: var(--ap-outline);">arrow_forward</span>
            </div>
            <div>
              <h3 class="customer-tile-title">Laporan Audit</h3>
              <span class="customer-tile-subtitle">Arsip Suhu BPOM</span>
            </div>
          </div>

          <!-- Action 4: Hotline 24 Jam -->
          <div
            class="customer-action-tile"
            style="background: linear-gradient(180deg, rgba(216, 30, 91, 0.15) 0%, #ffffff 100%);"
            @click="activeModal = 'hotline'"
          >
            <div class="customer-tile-top">
              <div class="customer-tile-icon" style="background: var(--ap-primary-container);">
                <span class="material-symbols-outlined" style="font-size: 24px;">support_agent</span>
              </div>
              <span class="material-symbols-outlined" style="color: var(--ap-outline);">arrow_forward</span>
            </div>
            <div>
              <h3 class="customer-tile-title">Hotline 24 Jam</h3>
              <span class="customer-tile-subtitle">Konsultasi Apoteker</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Banner Edukasi & Standar Keamanan Rantai Dingin -->
      <section class="customer-banner-card">
        <div class="customer-banner-left">
          <div class="customer-banner-icon">
            <span class="material-symbols-outlined" style="font-size: 28px;">shield_with_heart</span>
          </div>
          <div>
            <div style="display: flex; align-items: center; gap: 0.5rem;">
              <span class="customer-banner-title">Standar Keamanan Rantai Dingin</span>
              <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 9999px; background: var(--ap-secondary-container); color: var(--ap-secondary-dark);">
                ISO 17025
              </span>
            </div>
            <p class="customer-banner-desc">
              Kompresor ganda &amp; Cold Contingency Response tervalidasi BPOM RI.
            </p>
          </div>
        </div>
        <div>
          <button
            type="button"
            class="customer-chip-btn"
            style="padding: 0.55rem 1.15rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;"
            @click="activeModal = 'validation'"
          >
            <span>Dokumen Validasi</span>
            <span class="material-symbols-outlined" style="font-size: 16px; color: var(--ap-secondary);">verified</span>
          </button>
        </div>
      </section>
    </div>

    <!-- Modals for Interactive Experience -->
    <div v-if="activeModal" class="customer-modal-backdrop" @click.self="activeModal = null">
      <!-- Pick-up Modal -->
      <div v-if="activeModal === 'pickup'" class="customer-modal-card">
        <button class="customer-modal-close" @click="activeModal = null">✕</button>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
          <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: var(--ap-secondary-container); color: var(--ap-secondary-dark); display: flex; align-items: center; justify-content: center;">
            <span class="material-symbols-outlined" style="font-size: 24px;">rv_hookup</span>
          </div>
          <div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Permintaan Pick-Up Khusus</h3>
            <span style="font-size: 0.75rem; color: var(--ap-on-variant);">Reefer Box Suhu Dingin (2°C – 8°C)</span>
          </div>
        </div>
        <p style="font-size: 0.875rem; color: var(--ap-on-variant); line-height: 1.5; margin-bottom: 1rem;">
          Fasilitas Anda dapat mengajukan armada berpendingin untuk pengembalian sampel darah, retur obat termolabil, atau distribusi antar cabang faskes.
        </p>
        <div style="background: var(--ap-surface-low); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.25rem;">
          <div style="font-size: 0.75rem; font-weight: 700; color: var(--ap-on-surface); margin-bottom: 0.35rem;">Fasilitas Pemohon:</div>
          <div style="font-size: 0.875rem; font-weight: 600; color: var(--ap-primary);">{{ currentUser?.name }}</div>
          <div style="font-size: 0.75rem; color: var(--ap-on-variant); margin-top: 0.25rem;">Standar box: Active Reefer Carrier dengan sensor IoT terkalibrasi.</div>
        </div>
        <button
          type="button"
          class="customer-search-btn"
          style="width: 100%; justify-content: center;"
          @click="notify('Permintaan armada khusus telah diteruskan ke Logistics Dispatcher Anteraja Pharma!')"
        >
          Konfirmasi Pengajuan Armada
        </button>
      </div>

      <!-- Audit Modal -->
      <div v-if="activeModal === 'audit'" class="customer-modal-card">
        <button class="customer-modal-close" @click="activeModal = null">✕</button>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
          <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: var(--ap-surface-high); color: var(--ap-on-surface); display: flex; align-items: center; justify-content: center;">
            <span class="material-symbols-outlined" style="font-size: 24px;">description</span>
          </div>
          <div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Laporan Audit Kepatuhan Suhu</h3>
            <span style="font-size: 0.75rem; color: var(--ap-on-variant);">Standar BPOM CDOB &amp; ISO 9001</span>
          </div>
        </div>
        <p style="font-size: 0.875rem; color: var(--ap-on-variant); line-height: 1.5; margin-bottom: 1rem;">
          Dokumen rekam jejak telemetri suhu (2–8°C) dan berita acara serah terima (BAP) tersimpan secara digital dengan stempel waktu yang terenkripsi.
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.25rem;">
          <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--ap-surface-low); border-radius: 0.5rem; font-size: 0.8125rem;">
            <span>Kepatuhan Suhu Bulan Ini</span>
            <strong style="color: var(--ap-secondary);">99.98% Sesuai Standar</strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--ap-surface-low); border-radius: 0.5rem; font-size: 0.8125rem;">
            <span>Total Kargo Diterima</span>
            <strong>{{ totalActive }} Pengiriman</strong>
          </div>
        </div>
        <button
          type="button"
          class="customer-search-btn"
          style="width: 100%; justify-content: center; background: var(--ap-secondary);"
          @click="notify('Mengunduh Laporan Ringkasan Kepatuhan Suhu Faskes (PDF)...')"
        >
          Unduh Laporan Audit (PDF)
        </button>
      </div>

      <!-- Hotline Modal -->
      <div v-if="activeModal === 'hotline'" class="customer-modal-card">
        <button class="customer-modal-close" @click="activeModal = null">✕</button>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
          <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: var(--ap-primary-soft); color: var(--ap-primary); display: flex; align-items: center; justify-content: center;">
            <span class="material-symbols-outlined" style="font-size: 24px;">support_agent</span>
          </div>
          <div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Hotline Farmasi 24 Jam</h3>
            <span style="font-size: 0.75rem; color: var(--ap-on-variant);">Tim Apoteker &amp; Cold-Chain Specialist</span>
          </div>
        </div>
        <p style="font-size: 0.875rem; color: var(--ap-on-variant); line-height: 1.5; margin-bottom: 1rem;">
          Hubungi tim spesialis logistik farmasi Anteraja untuk penanganan darurat suhu, konfirmasi waktu tiba kargo biologis kritis, atau klarifikasi produk:
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1.25rem;">
          <a
            href="https://wa.me/6281200000000?text=Halo%20Tim%20Anteraja%20Pharma%2C%20saya%20dari%20faskes"
            target="_blank"
            style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: 0.75rem; background: var(--ap-secondary-soft); color: var(--ap-secondary-dark); text-decoration: none; font-weight: 700; font-size: 0.875rem;"
          >
            <span style="display: flex; align-items: center; gap: 0.5rem;">
              <span class="material-symbols-outlined">chat</span>
              WhatsApp Hotline Farmasi
            </span>
            <span>+62 812-PHARMA-1</span>
          </a>
          <a
            href="tel:021500000"
            style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; border-radius: 0.75rem; background: var(--ap-surface-low); color: var(--ap-on-surface); text-decoration: none; font-weight: 700; font-size: 0.875rem;"
          >
            <span style="display: flex; align-items: center; gap: 0.5rem;">
              <span class="material-symbols-outlined">call</span>
              Pusat Kendali Dispatcher
            </span>
            <span>(021) 500-PHARMA</span>
          </a>
        </div>
      </div>

      <!-- SOP Modal -->
      <div v-if="activeModal === 'sop' || activeModal === 'validation'" class="customer-modal-card">
        <button class="customer-modal-close" @click="activeModal = null">✕</button>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
          <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: var(--ap-secondary-container); color: var(--ap-secondary-dark); display: flex; align-items: center; justify-content: center;">
            <span class="material-symbols-outlined" style="font-size: 24px;">verified_user</span>
          </div>
          <div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Standar CDOB &amp; Sertifikasi Mutu</h3>
            <span style="font-size: 0.75rem; color: var(--ap-on-variant);">WHO TRS 961 • ISO 9001:2015 • BPOM RI</span>
          </div>
        </div>
        <p style="font-size: 0.875rem; color: var(--ap-on-variant); line-height: 1.5; margin-bottom: 1rem;">
          Setiap unit pengiriman Anteraja Pharma diawasi oleh Apoteker Penanggung Jawab (APJ) dengan sertifikasi Cara Distribusi Obat yang Baik (CDOB). Pemantauan suhu menggunakan data logger tersertifikasi kalibrasi KAN.
        </p>
        <button
          type="button"
          class="customer-search-btn"
          style="width: 100%; justify-content: center;"
          @click="activeModal = null"
        >
          Tutup
        </button>
      </div>
    </div>
  </div>
</template>
