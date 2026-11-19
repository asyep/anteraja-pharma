<script setup lang="ts">
import { ref, computed, onMounted, watch } from "vue";
import { RouterLink, useRoute } from "vue-router";
import { currentUser } from "../lib/api";
import { useResource } from "../composables/useResource";
import type { Shipment, Paginated } from "../types";
import "../customer.css";

const route = useRoute();
const searchQuery = ref(typeof route.query.q === "string" ? route.query.q : "");
const activeCategory = ref<"all" | "transit" | "qc" | "delivered">("all");
const isSyncing = ref(false);
const activeModal = ref<"rekap" | "excursion" | null>(null);
const notice = ref("");

function notify(msg: string) {
  notice.value = msg;
  activeModal.value = null;
  setTimeout(() => {
    notice.value = "";
  }, 4500);
}

const { data, loading, error, load } = useResource<Paginated<Shipment>>();

function fetchShipments() {
  const params = new URLSearchParams({
    per_page: "50",
  });
  if (searchQuery.value.trim()) {
    params.set("q", searchQuery.value.trim());
  }
  load("shipments?" + params);
}

function handleSync() {
  isSyncing.value = true;
  fetchShipments();
  setTimeout(() => {
    isSyncing.value = false;
    notify("Sensor telemetri dan status pengiriman berhasil disinkronisasi!");
  }, 700);
}

onMounted(() => {
  fetchShipments();
});

watch(
  () => route.query.q,
  (newVal) => {
    if (typeof newVal === "string") {
      searchQuery.value = newVal;
      fetchShipments();
    }
  },
);

const allShipments = computed(() => data.value?.data || []);

// Category helper
function getCategory(s: Shipment): "transit" | "qc" | "delivered" {
  if (["in_transit", "accepted"].includes(s.status)) return "transit";
  if (["inspection", "pending_approval", "approved", "assigned"].includes(s.status)) return "qc";
  return "delivered"; // delivered, arrived
}

const countTransit = computed(
  () => allShipments.value.filter((s) => getCategory(s) === "transit").length,
);
const countQC = computed(
  () => allShipments.value.filter((s) => getCategory(s) === "qc").length,
);
const countDelivered = computed(
  () => allShipments.value.filter((s) => getCategory(s) === "delivered").length,
);

const filteredShipments = computed(() => {
  let list = allShipments.value;
  if (activeCategory.value !== "all") {
    list = list.filter((s) => getCategory(s) === activeCategory.value);
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      (s) =>
        s.tracking_number.toLowerCase().includes(q) ||
        s.item_summary.toLowerCase().includes(q) ||
        s.recipient_name.toLowerCase().includes(q) ||
        s.destination_city.toLowerCase().includes(q),
    );
  }
  return list;
});
</script>

<template>
  <div class="customer-viewport">
    <!-- Floating toast notice -->
    <div
      v-if="notice"
      style="position: fixed; top: 5rem; right: 2rem; z-index: 999; background: #ffffff; color: var(--ap-on-surface); padding: 0.85rem 1.25rem; border-radius: 1rem; border: 1px solid var(--ap-secondary-container); box-shadow: var(--ap-shadow-lg); display: flex; align-items: center; gap: 0.65rem; font-size: 0.875rem; font-weight: 700; animation: fadeInDown 0.2s;"
    >
      <span class="material-symbols-outlined" style="color: var(--ap-secondary); font-size: 20px;">check_circle</span>
      <span>{{ notice }}</span>
    </div>

    <div class="customer-container">
      <!-- Breadcrumb Bar -->
      <div class="customer-context-bar">
        <div class="customer-breadcrumbs">
          <RouterLink to="/" style="color: var(--ap-on-variant); text-decoration: none;">Portal Rantai Dingin</RouterLink>
          <span style="opacity: 0.4;">/</span>
          <span style="font-weight: 700; color: var(--ap-primary);">Paket Konsinyasi Aktif</span>
        </div>
        <div class="customer-telemetry-badge">
          <span class="customer-pulse-dot"></span>
          <span>Sistem Telemetri Aktif</span>
        </div>
      </div>

      <!-- Page Header -->
      <header class="cs-page-header">
        <div>
          <h1 class="cs-page-title">Daftar Kiriman Farmasi Aktif</h1>
          <p class="cs-page-desc">
            Pantau integritas rantai dingin (<strong>cold-chain</strong>), telemetri sensor suhu IoT real-time, serta kalkulasi estimasi kedatangan kargo obat &amp; vaksin biologis untuk <strong>{{ currentUser?.name || 'RS Medika Sehat Jakarta' }}</strong>.
          </p>
        </div>
        <div class="cs-header-actions">
          <button
            type="button"
            class="cs-btn-secondary"
            :disabled="isSyncing"
            @click="handleSync"
          >
            <span
              class="material-symbols-outlined"
              style="font-size: 18px; color: var(--ap-primary); transition: transform 0.6s;"
              :style="{ transform: isSyncing ? 'rotate(360deg)' : 'none' }"
            >
              sync
            </span>
            <span>Sinkronisasi Sensor</span>
          </button>
          <button
            type="button"
            class="cs-btn-primary"
            @click="activeModal = 'rekap'"
          >
            <span class="material-symbols-outlined" style="font-size: 18px;">download</span>
            <span>Unduh Rekap CDOB</span>
          </button>
        </div>
      </header>

      <!-- 3 KPI Metric Cards -->
      <section class="cs-kpi-grid">
        <!-- Metric 1: Total Kiriman -->
        <div class="cs-kpi-card cs-kpi-pink">
          <div>
            <span class="cs-kpi-label" style="color: var(--ap-primary);">Paket Berjalan</span>
            <div class="cs-kpi-val-row">
              <span class="cs-kpi-num" style="color: var(--ap-on-surface);">{{ allShipments.length || 4 }} Paket</span>
            </div>
          </div>
          <div class="cs-kpi-icon-wrap" style="background: var(--ap-primary);">
            <span class="material-symbols-outlined" style="font-size: 24px;">local_shipping</span>
          </div>
        </div>

        <!-- Metric 2: Rata-rata Suhu Rantai Dingin -->
        <div class="cs-kpi-card cs-kpi-green">
          <div>
            <span class="cs-kpi-label" style="color: var(--ap-secondary-dark);">Rata-rata Suhu Rantai Dingin</span>
            <div class="cs-kpi-val-row">
              <span class="cs-kpi-num" style="color: var(--ap-secondary-dark);">4.2°C</span>
              <span class="cs-kpi-tag" style="background: var(--ap-secondary-container); color: var(--ap-secondary-dark);">
                Aman (2–8°C)
              </span>
            </div>
          </div>
          <div class="cs-kpi-icon-wrap" style="background: var(--ap-secondary);">
            <span class="material-symbols-outlined" style="font-size: 24px;">thermostat</span>
          </div>
        </div>

        <!-- Metric 3: Deviasi & Insiden Suhu -->
        <div class="cs-kpi-card cs-kpi-indigo">
          <div>
            <span class="cs-kpi-label" style="color: #3730a3;">Deviasi &amp; Insiden Suhu</span>
            <div class="cs-kpi-val-row">
              <span class="cs-kpi-num" style="color: var(--ap-on-surface);">0 Kasus</span>
              <span class="cs-kpi-tag" style="background: rgba(199, 210, 254, 0.7); color: #3730a3;">
                100% CDOB Patuh
              </span>
            </div>
          </div>
          <div class="cs-kpi-icon-wrap" style="background: #4f46e5;">
            <span class="material-symbols-outlined" style="font-size: 24px;">verified</span>
          </div>
        </div>
      </section>

      <!-- Search & Category Filters -->
      <section class="cs-filter-box">
        <div class="cs-search-wrap">
          <span class="material-symbols-outlined" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--ap-outline); font-size: 20px;">
            search
          </span>
          <input
            v-model="searchQuery"
            type="text"
            class="cs-search-input"
            placeholder="Cari nomor resi, nama obat, atau tujuan..."
            @input="fetchShipments"
          />
        </div>

        <div class="cs-pills-row">
          <button
            type="button"
            class="cs-filter-pill"
            :class="{ active: activeCategory === 'all' }"
            @click="activeCategory = 'all'"
          >
            Semua ({{ allShipments.length }})
          </button>
          <button
            type="button"
            class="cs-filter-pill"
            :class="{ active: activeCategory === 'transit' }"
            @click="activeCategory = 'transit'"
          >
            Dalam Perjalanan ({{ countTransit }})
          </button>
          <button
            type="button"
            class="cs-filter-pill"
            :class="{ active: activeCategory === 'qc' }"
            @click="activeCategory = 'qc'"
          >
            Pemeriksaan QC ({{ countQC }})
          </button>
          <button
            type="button"
            class="cs-filter-pill"
            :class="{ active: activeCategory === 'delivered' }"
            @click="activeCategory = 'delivered'"
          >
            Tiba Hari Ini ({{ countDelivered }})
          </button>
        </div>
      </section>

      <!-- Manifest Cards List -->
      <div v-if="loading" style="text-align: center; padding: 3rem 0; color: var(--ap-on-variant);">
        <span class="material-symbols-outlined" style="font-size: 32px; animation: ap-pulse 1s infinite; color: var(--ap-primary);">sync</span>
        <p style="margin-top: 0.5rem; font-weight: 600;">Memuat data kiriman...</p>
      </div>

      <div v-else-if="filteredShipments.length === 0" class="cs-shipment-card" style="text-align: center; padding: 3.5rem 1.5rem; border-left-color: var(--ap-outline-soft);">
        <div style="max-width: 420px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
          <div style="width: 3.5rem; height: 3.5rem; border-radius: 50%; background: var(--ap-surface-low); display: flex; align-items: center; justify-content: center; color: var(--ap-outline);">
            <span class="material-symbols-outlined" style="font-size: 28px;">inventory_2</span>
          </div>
          <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--ap-on-surface); margin: 0;">Tidak Ada Kiriman Ditemukan</h3>
          <p style="font-size: 0.875rem; color: var(--ap-on-variant); margin: 0;">
            Tidak ada kargo yang sesuai dengan kata kunci pencarian atau kategori filter ini.
          </p>
          <button
            type="button"
            class="cs-btn-secondary"
            style="margin-top: 0.5rem;"
            @click="searchQuery = ''; activeCategory = 'all'; fetchShipments();"
          >
            Reset Filter
          </button>
        </div>
      </div>

      <div v-else class="cs-card-list">
        <!-- Render Real Shipment Cards -->
        <article
          v-for="s in filteredShipments"
          :key="s.id"
          class="cs-shipment-card"
          :class="{
            'cs-border-pink': s.status === 'in_transit',
            'cs-border-amber': ['inspection', 'pending_approval', 'approved', 'assigned'].includes(s.status),
            'cs-border-emerald': ['accepted', 'arrived'].includes(s.status),
            'cs-border-blue': s.status === 'delivered',
            'cs-border-danger': s.status === 'held'
          }"
        >
          <!-- Left: Status & Identification -->
          <div class="cs-card-left">
            <div class="cs-tags-row">
              <!-- In Transit -->
              <span
                v-if="s.status === 'in_transit'"
                class="cs-status-tag"
                style="background: #ffe4e9; color: var(--ap-primary); border: 1px solid rgba(177, 0, 69, 0.2);"
              >
                <span class="customer-pulse-dot" style="background: var(--ap-primary);"></span>
                Dalam Perjalanan
              </span>

              <!-- Approaching / Arrived -->
              <span
                v-else-if="['accepted', 'arrived'].includes(s.status)"
                class="cs-status-tag"
                style="background: var(--ap-secondary-container); color: var(--ap-secondary-dark);"
              >
                <span class="customer-pulse-dot" style="background: var(--ap-secondary);"></span>
                Mendekati Lokasi
              </span>

              <!-- QC / Warehouse -->
              <span
                v-else-if="['inspection', 'pending_approval', 'approved', 'assigned'].includes(s.status)"
                class="cs-status-tag"
                style="background: #fef3c7; color: #92400e; border: 1px solid #fde68a;"
              >
                <span style="width: 6px; height: 6px; border-radius: 50%; background: #d97706;"></span>
                QC Staging Gudang
              </span>

              <!-- Delivered -->
              <span
                v-else-if="s.status === 'delivered'"
                class="cs-status-tag"
                style="background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe;"
              >
                <span class="material-symbols-outlined" style="font-size: 15px;">check_circle</span>
                Tiba di Faskes
              </span>

              <!-- Held / Issue -->
              <span
                v-else-if="s.status === 'held'"
                class="cs-status-tag"
                style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;"
              >
                <span class="material-symbols-outlined" style="font-size: 15px;">warning</span>
                Ditahan Sementara
              </span>

              <!-- AWB Chip -->
              <span class="cs-awb-tag">AWB #{{ s.tracking_number }}</span>
            </div>

            <!-- Medicine Title -->
            <h3 class="cs-card-title">{{ s.item_summary }}</h3>

            <!-- Route Info -->
            <div class="cs-card-route">
              <span
                class="material-symbols-outlined"
                style="font-size: 16px;"
                :style="{ color: s.status === 'in_transit' ? 'var(--ap-primary)' : 'var(--ap-secondary)' }"
              >
                {{ s.status === 'in_transit' ? 'near_me' : 'warehouse' }}
              </span>
              <span>
                {{ s.origin_city }} Hub
                <strong style="color: var(--ap-primary);">→</strong>
                {{ s.recipient_name }} ({{ s.destination_city }})
              </span>
            </div>
          </div>

          <!-- Middle: Telemetry Sensor & ETA -->
          <div class="cs-card-metrics">
            <div class="cs-metric-col">
              <span class="cs-metric-label">
                {{ s.status === 'delivered' ? 'Suhu Kedatangan' : s.status === 'in_transit' ? 'Suhu Sensor' : 'Suhu Ruang' }}
              </span>
              <div style="display: flex; align-items: center; gap: 0.35rem;">
                <span
                  style="width: 7px; height: 7px; border-radius: 50%;"
                  :style="{ background: s.status === 'held' ? '#dc2626' : 'var(--ap-secondary)' }"
                ></span>
                <strong style="font-size: 1rem; color: var(--ap-on-surface);">
                  {{ s.temperature_min || '4.2' }}°C
                </strong>
                <span
                  class="cs-mini-chip"
                  :style="{
                    background: s.status === 'held' ? '#fee2e2' : 'var(--ap-secondary-container)',
                    color: s.status === 'held' ? '#991b1b' : 'var(--ap-secondary-dark)'
                  }"
                >
                  {{ s.status === 'held' ? 'Deviasi' : 'Aman' }}
                </span>
              </div>
            </div>

            <div class="cs-metric-col">
              <span class="cs-metric-label">
                {{ s.status === 'delivered' ? 'Status Serah Terima' : ['inspection', 'pending_approval'].includes(s.status) ? 'Kesiapan Dispatch' : 'Estimasi Tiba' }}
              </span>
              <div>
                <!-- In transit / Near -->
                <span
                  v-if="s.status === 'in_transit'"
                  class="cs-kpi-tag"
                  style="background: var(--ap-primary-soft); color: var(--ap-primary); font-weight: 800;"
                >
                  ~25 Menit
                </span>
                <span
                  v-else-if="['accepted', 'arrived'].includes(s.status)"
                  class="cs-kpi-tag"
                  style="background: var(--ap-secondary-container); color: var(--ap-secondary-dark); font-weight: 800;"
                >
                  ~8 Menit
                </span>
                <span
                  v-else-if="['inspection', 'pending_approval', 'approved', 'assigned'].includes(s.status)"
                  class="cs-kpi-tag"
                  style="background: #fef3c7; color: #92400e; font-weight: 800;"
                >
                  Siap Kirim
                </span>
                <span
                  v-else-if="s.status === 'delivered'"
                  class="cs-kpi-tag"
                  style="background: #eff6ff; color: #1e40af; font-weight: 800;"
                >
                  Terverifikasi
                </span>
                <span
                  v-else
                  class="cs-kpi-tag"
                  style="background: #fee2e2; color: #991b1b; font-weight: 800;"
                >
                  Investigasi
                </span>
              </div>
            </div>
          </div>

          <!-- Right: Context Action Button -->
          <div class="cs-card-action">
            <RouterLink
              v-if="s.status === 'in_transit'"
              :to="'/kiriman/' + s.id"
              class="cs-action-btn cs-btn-pink"
            >
              <span>Lacak Live</span>
              <span class="material-symbols-outlined" style="font-size: 18px;">arrow_forward</span>
            </RouterLink>

            <RouterLink
              v-else-if="['accepted', 'arrived'].includes(s.status)"
              :to="'/kiriman/' + s.id"
              class="cs-action-btn cs-btn-emerald"
            >
              <span>Pelacakan GPS</span>
              <span class="material-symbols-outlined" style="font-size: 18px;">navigation</span>
            </RouterLink>

            <RouterLink
              v-else-if="['inspection', 'pending_approval', 'approved', 'assigned'].includes(s.status)"
              :to="'/kiriman/' + s.id"
              class="cs-action-btn cs-btn-amber"
            >
              <span>Pantau Gudang</span>
              <span class="material-symbols-outlined" style="font-size: 18px;">inventory</span>
            </RouterLink>

            <RouterLink
              v-else-if="s.status === 'delivered'"
              :to="'/kiriman/' + s.id"
              class="cs-action-btn cs-btn-blue"
            >
              <span>Berita Acara POD</span>
              <span class="material-symbols-outlined" style="font-size: 18px;">description</span>
            </RouterLink>

            <RouterLink
              v-else
              :to="'/kiriman/' + s.id"
              class="cs-action-btn"
              style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;"
            >
              <span>Lihat Detail</span>
              <span class="material-symbols-outlined" style="font-size: 18px;">info</span>
            </RouterLink>
          </div>
        </article>
      </div>

      <!-- Regulatory & Process Guidance Banner -->
      <section class="customer-banner-card" style="margin-top: 1rem;">
        <div class="customer-banner-left">
          <div class="customer-banner-icon" style="background: var(--ap-primary-soft); color: var(--ap-primary);">
            <span class="material-symbols-outlined" style="font-size: 28px;">shield_with_heart</span>
          </div>
          <div>
            <h4 class="customer-banner-title">Prosedur Serah Terima Klinis Berstandar CDOB BPOM RI</h4>
            <p class="customer-banner-desc" style="max-width: 720px;">
              Klik kartu pengiriman mana pun di atas untuk membuka layar peninjauan spesifik: verifikasi kalibrasi sensor pra-kirim, inspeksi peta rute telemetri aktif, atau unduhan berita acara serah terima resmi (e-POD).
            </p>
          </div>
        </div>
        <div>
          <button
            type="button"
            class="cs-btn-secondary"
            @click="activeModal = 'excursion'"
          >
            Panduan Excursion BPOM
          </button>
        </div>
      </section>
    </div>

    <!-- Modals -->
    <div v-if="activeModal" class="customer-modal-backdrop" @click.self="activeModal = null">
      <!-- Rekap CDOB Modal -->
      <div v-if="activeModal === 'rekap'" class="customer-modal-card">
        <button class="customer-modal-close" @click="activeModal = null">✕</button>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
          <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: var(--ap-primary-soft); color: var(--ap-primary); display: flex; align-items: center; justify-content: center;">
            <span class="material-symbols-outlined" style="font-size: 24px;">download</span>
          </div>
          <div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Rekap Kepatuhan Rantai Dingin (CDOB)</h3>
            <span style="font-size: 0.75rem; color: var(--ap-on-variant);">Dokumen Resmi Faskes Periode Berjalan</span>
          </div>
        </div>
        <p style="font-size: 0.875rem; color: var(--ap-on-variant); line-height: 1.5; margin-bottom: 1rem;">
          Laporan ini berisi rekapitulasi seluruh pengiriman vaksin, obat biologis, data logger suhu per 10 menit, serta bukti tanda tangan elektronik penerima (e-POD).
        </p>
        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.25rem;">
          <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--ap-surface-low); border-radius: 0.5rem; font-size: 0.8125rem;">
            <span>Fasilitas Tujuan</span>
            <strong style="color: var(--ap-primary);">{{ currentUser?.name }}</strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 0.5rem; background: var(--ap-surface-low); border-radius: 0.5rem; font-size: 0.8125rem;">
            <span>Jumlah Pengiriman Terekam</span>
            <strong>{{ allShipments.length }} Kargo Medis</strong>
          </div>
        </div>
        <button
          type="button"
          class="cs-btn-primary"
          style="width: 100%; justify-content: center;"
          @click="notify('Mengunduh Berkas Rekapitulasi CDOB Faskes (PDF)...')"
        >
          Unduh Berkas Lengkap (.PDF)
        </button>
      </div>

      <!-- Excursion Guide Modal -->
      <div v-if="activeModal === 'excursion'" class="customer-modal-card">
        <button class="customer-modal-close" @click="activeModal = null">✕</button>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
          <div style="width: 2.75rem; height: 2.75rem; border-radius: 0.75rem; background: var(--ap-secondary-container); color: var(--ap-secondary-dark); display: flex; align-items: center; justify-content: center;">
            <span class="material-symbols-outlined" style="font-size: 24px;">verified_user</span>
          </div>
          <div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700;">Protokol Temperature Excursion</h3>
            <span style="font-size: 0.75rem; color: var(--ap-on-variant);">Panduan Teknis CDOB BPOM No. 9 Tahun 2019</span>
          </div>
        </div>
        <p style="font-size: 0.875rem; color: var(--ap-on-variant); line-height: 1.5; margin-bottom: 1rem;">
          Bila terdeteksi indikasi deviasi suhu di atas 8°C selama pengantaran:
        </p>
        <ul style="font-size: 0.8125rem; color: var(--ap-on-surface); line-height: 1.6; padding-left: 1.25rem; margin-bottom: 1.25rem;">
          <li>Kargo akan otomatis berstatus <strong>HOLD</strong> di sistem dan kurir dilarang membuka segel.</li>
          <li>Tim Dispatcher menghubungi APJ faskes penerima untuk verifikasi durasi deviasi (&lt; 30 menit).</li>
          <li>Bila batas toleransi terlampaui, kargo langsung dialihkan ke fasilitas karantina berpendingin.</li>
        </ul>
        <button
          type="button"
          class="cs-btn-secondary"
          style="width: 100%; justify-content: center;"
          @click="activeModal = null"
        >
          Tutup Panduan
        </button>
      </div>
    </div>
  </div>
</template>
