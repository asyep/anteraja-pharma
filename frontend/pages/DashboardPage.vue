<script setup lang="ts">
  import { computed, onMounted } from "vue";
  import { RouterLink } from "vue-router";
  import { ArrowRightIcon, PlusIcon, ShieldCheckIcon } from "@heroicons/vue/16/solid";
  import { currentUser } from "../lib/api";
  import { useResource } from "../composables/useResource";
  import type { Shipment } from "../types";
  import StatusLabel from "../components/StatusLabel.vue";
  import ResourceState from "../components/ResourceState.vue";
  import { dateTime, nextStep } from "../lib/presentation";
  const { data, loading, error, load } = useResource<{
    totals: Record<string, number>;
    priority: Shipment[];
  }>();
  const admin = computed(() => currentUser.value?.role === "admin");
  const courier = computed(() => currentUser.value?.role === "courier");
  const total = computed(() =>
    Object.values(data.value?.totals || {}).reduce((a, b) => a + Number(b), 0),
  );
  const day = new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    timeZone: "Asia/Jakarta",
  }).format(new Date());
  const stats = computed(() => [
    {
      label: "Kiriman tercatat",
      value: total.value,
      hint: "Seluruh tahap pengiriman",
      status: "",
    },
    {
      label: "Perlu diperiksa",
      value: (data.value?.totals.inspection || 0) + (data.value?.totals.pending_approval || 0),
      hint: "Pemeriksaan dan persetujuan",
      status: "warning",
    },
    {
      label: "Ditahan sementara",
      value: data.value?.totals.held || 0,
      hint: "Menunggu keputusan Admin",
      status: "danger",
    },
    {
      label: "Sudah diterima",
      value: data.value?.totals.delivered || 0,
      hint: "Serah terima tercatat",
      status: "success",
    },
  ]);
  onMounted(() => load("dashboard"));
</script>
<template>
  <section class="page-heading">
    <div>
      <p class="overline">{{ day }}</p>
      <h1>
        {{
          admin
            ? "Yang perlu ditangani hari ini."
            : courier
              ? "Tugasmu, langkah demi langkah."
              : "Paket kesehatanmu, terpantau."
        }}
      </h1>
      <p class="muted">
        {{
          admin
            ? "Selamat bekerja, " +
              currentUser?.name.split(" ")[0] +
              ". Pastikan setiap paket berangkat dengan pemeriksaan yang lengkap."
            : courier
              ? "Periksa paket, ikuti instruksi, dan laporkan kendala sebelum melanjutkan."
              : "Lihat kabar paket, siapkan penerimaan, dan hubungi tim bila ada kendala."
        }}
      </p>
    </div>
    <RouterLink v-if="admin" class="button primary" to="/kiriman/baru">
      <PlusIcon />
      Buat kiriman
    </RouterLink>
  </section>
  <ResourceState :loading="loading" :error="error" @retry="load('dashboard')" />
  <template v-if="data && !loading">
    <section class="metrics" aria-label="Ringkasan kiriman">
      <article v-for="s in stats" :key="s.label" :class="s.status">
        <p>{{ s.label }}</p>
        <h2>{{ s.value }}</h2>
        <p class="small">{{ s.hint }}</p>
      </article>
    </section>
    <section>
      <header class="section-heading">
        <div>
          <p class="overline">Langkah berikutnya</p>
          <h2>
            {{
              admin
                ? "Tangani yang menunggu."
                : courier
                  ? "Cek tugas yang perlu jawaban."
                  : "Ada kabar untuk paketmu."
            }}
          </h2>
        </div>
        <RouterLink to="/kiriman" class="text-link">
          Lihat semua kiriman
          <ArrowRightIcon />
        </RouterLink>
      </header>
      <div v-if="!data.priority.length" class="empty-state">
        <h3>Tidak ada kiriman yang menunggu tindakan.</h3>
        <p>Pembaruan selanjutnya akan tampil di daftar kiriman.</p>
        <RouterLink to="/kiriman" class="button secondary">Buka pengiriman</RouterLink>
      </div>
      <div v-else class="priority-list">
        <RouterLink
          v-for="s in data.priority"
          :key="s.id"
          :to="'/kiriman/' + s.id"
          class="priority-row"
        >
          <div>
            <p class="weight">
              {{ s.tracking_number }}
              <span class="small muted">· {{ s.recipient_name }}</span>
            </p>
            <p class="small muted">{{ nextStep(s) }}</p>
          </div>
          <div>
            <StatusLabel :status="s.status" />
            <p class="small muted">{{ s.destination_city }} · {{ dateTime(s.scheduled_at) }}</p>
          </div>
          <ArrowRightIcon aria-hidden="true" />
        </RouterLink>
      </div>
    </section>
    <section class="dashboard-feature">
      <div class="feature-copy">
        <p class="overline">Kualitas sebelum kecepatan</p>
        <h2>
          Paket belum siap?
          <br />
          Jangan dipaksa berangkat.
        </h2>
        <p>
          Kemasan, segel, dan label harus diperiksa. Keputusan Admin menjaga langkah berikutnya
          tetap jelas.
        </p>
        <RouterLink to="/kiriman?status=pending_approval" class="text-link">
          Lihat yang menunggu persetujuan
          <ArrowRightIcon />
        </RouterLink>
      </div>
      <img
        src="/images/sorting.webp"
        alt="Petugas memindai paket di fasilitas pengiriman"
        width="650"
        height="400"
      />
      <p class="feature-caption">
        <ShieldCheckIcon />
        Pemeriksaan tercatat sebelum penugasan
      </p>
    </section>
    <section class="process-overview">
      <p class="overline">Satu alur, tidak ada langkah terlewat</p>
      <ol role="list">
        <li>
          <span>01</span>
          Periksa paket
        </li>
        <li>
          <span>02</span>
          Admin menyetujui
        </li>
        <li>
          <span>03</span>
          Satria menerima tugas
        </li>
        <li>
          <span>04</span>
          Antar & catat hasil
        </li>
      </ol>
    </section>
  </template>
</template>
