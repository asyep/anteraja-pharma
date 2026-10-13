<script setup lang="ts">
  import { ref, onMounted } from "vue";
  import { DocumentArrowDownIcon, DocumentChartBarIcon } from "@heroicons/vue/16/solid";
  import { useResource } from "../composables/useResource";
  import { post } from "../lib/api";
  import { dateTime, labels } from "../lib/presentation";
  import type { Report, Paginated } from "../types";
  import ResourceState from "../components/ResourceState.vue";
  const { data, loading, error, load } = useResource<Paginated<Report>>();
  const today = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(new Date());
  const date = ref(today),
    busy = ref(false),
    notice = ref("");
  onMounted(() => load("reports"));
  async function generate() {
    if (busy.value) return;
    busy.value = true;
    notice.value = "";
    try {
      await post("reports", { date: date.value });
      notice.value =
        "Laporan tersimpan dan pesan Admin masuk antrean. Jika ada pembaruan, sistem membuat revisi tanpa mengubah laporan lama.";
      await load("reports");
    } catch (e) {
      notice.value = (e as Error).message;
    } finally {
      busy.value = false;
    }
  }
</script>
<template>
  <section class="page-heading">
    <div>
      <p class="overline">Bukti, bukan ingatan</p>
      <h1>Satu hari. Seluruh jejaknya.</h1>
      <p class="muted">
        Laporan merangkum kiriman, kendala, hasil pemeriksaan, dan keputusan. Dibuat otomatis setiap
        pukul 17.00 WIB saat scheduler berjalan.
      </p>
    </div>
  </section>
  <form class="report-form panel" @submit.prevent="generate">
    <div>
      <label for="report-date">Tanggal laporan</label>
      <input id="report-date" v-model="date" name="date" type="date" :max="today" required />
    </div>
    <button class="button primary" type="submit" :disabled="busy">
      <DocumentChartBarIcon />
      Buat laporan
      <span v-if="busy" class="spinner" aria-label="Membuat laporan" />
    </button>
    <p class="small muted">Snapshot tidak menimpa laporan yang sudah dibuat.</p>
  </form>
  <p v-if="notice" class="notice neutral" role="status">{{ notice }}</p>
  <ResourceState
    :loading="loading"
    :error="error"
    label="Memuat laporan operasional…"
    @retry="load('reports')"
  />
  <section v-if="data && !loading">
    <div v-if="!data.data.length" class="empty-state">
      <h2>Belum ada laporan harian.</h2>
      <p>Buat laporan untuk melihat ringkasan dan jejak pengiriman hari ini.</p>
    </div>
    <article v-for="r in data.data" :key="r.id" class="report-card">
      <header class="section-heading">
        <div>
          <p class="overline">Laporan harian</p>
          <h2>{{ r.report_date }}</h2>
          <p class="small muted">Revisi {{ r.revision }} · Dibuat {{ dateTime(r.created_at) }}</p>
        </div>
        <a :href="'/api/reports/' + r.id + '/export'" class="button secondary">
          <DocumentArrowDownIcon />
          Unduh riwayat CSV
        </a>
      </header>
      <dl class="report-metrics">
        <div>
          <dt>Kiriman tercatat</dt>
          <dd>{{ r.summary.total }}</dd>
        </div>
        <div>
          <dt>Kendala hari ini</dt>
          <dd>{{ r.summary.issues_today }}</dd>
        </div>
        <div>
          <dt>Berangkat diblokir</dt>
          <dd>{{ r.summary.blocked_dispatch_today }}</dd>
        </div>
        <div>
          <dt>Kegiatan tercatat</dt>
          <dd>{{ r.summary.events_today }}</dd>
        </div>
      </dl>
      <div class="report-distribution">
        <p v-for="(count, status) in r.summary.by_status" :key="status">
          <span>{{ labels[status] || status }}</span>
          <strong>{{ count }}</strong>
        </p>
      </div>
      <p class="small muted">
        CSV memuat resi, tahap, kota, pelaku, waktu, kegiatan, dan alasan. Bukti pemeriksaan dan
        keputusan lengkap tersimpan di snapshot database.
      </p>
    </article>
  </section>
</template>
